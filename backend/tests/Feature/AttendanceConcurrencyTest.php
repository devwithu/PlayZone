<?php

namespace Tests\Feature;

use App\Exceptions\ApiException;
use App\Models\Attendance;
use App\Models\PointTransaction;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Throwable;

/**
 * 동시 출석 요청 (REQ-902, 포인트 정책 §6). 프로세스 여러 개가 같은 순간 같은 회원으로 출석해도
 * 출석 행과 포인트가 한 번만 생기는지 실제 DB 연결 여러 개로 확인한다.
 *
 * 다른 프로세스가 데이터를 봐야 하므로 RefreshDatabase(트랜잭션)를 쓰지 않고, 만든 회원을 끝나고 지운다.
 */
class AttendanceConcurrencyTest extends TestCase
{
    private const WORKERS = 8;

    private ?int $userId = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl 확장이 필요합니다.');
        }

        if (! RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh');
            $this->app[Kernel::class]->setArtisan(null);
            RefreshDatabaseState::$migrated = true;
        }
    }

    protected function tearDown(): void
    {
        if ($this->userId !== null) {
            DB::table('point_transactions')->where('user_id', $this->userId)->delete();
            DB::table('users')->where('id', $this->userId)->delete();
        }

        parent::tearDown();
    }

    public function test_concurrent_check_ins_grant_points_once(): void
    {
        $user = User::factory()->create();
        $this->userId = $user->id;
        $dir = sys_get_temp_dir().'/attendance-race-'.getmypid();
        @mkdir($dir);
        $startAt = microtime(true) + 1.0;

        // 자식 프로세스가 부모의 DB 연결을 같이 쓰지 않도록 끊고 나눈다
        DB::disconnect();
        $pids = [];
        for ($i = 0; $i < self::WORKERS; $i++) {
            $pid = pcntl_fork();
            if ($pid === 0) {
                $this->runWorker($user->id, $startAt, "{$dir}/{$i}");
            }
            $pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
        }
        DB::reconnect();

        $results = [];
        foreach (glob("{$dir}/*") ?: [] as $file) {
            $results[] = file_get_contents($file);
            unlink($file);
        }
        rmdir($dir);
        sort($results);

        $this->assertSame(
            [...array_fill(0, self::WORKERS - 1, 'ATTENDANCE_ALREADY_DONE'), 'ok'],
            $results,
        );
        $this->assertSame(1, Attendance::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, PointTransaction::query()->where('user_id', $user->id)->count());
        $this->assertSame(10, $user->refresh()->point_balance);
    }

    /**
     * 자식 프로세스: 정해진 시각에 동시에 출석하고, 결과를 파일에 쓴 뒤 PHPUnit 정리 코드 없이 바로 끝난다.
     */
    private function runWorker(int $userId, float $startAt, string $resultFile): never
    {
        try {
            DB::reconnect();
            $user = User::query()->findOrFail($userId);
            time_sleep_until($startAt);
            app(AttendanceService::class)->check($user);
            $result = 'ok';
        } catch (ApiException $e) {
            $result = $e->errorCode->value;
        } catch (Throwable $e) {
            $result = 'error: '.$e->getMessage();
        }

        file_put_contents($resultFile, $result);
        posix_kill(posix_getpid(), SIGKILL);
        exit(1);
    }
}
