import { router, usePage } from '@inertiajs/vue3';
import { login } from '@/routes';
import {
    destroy as unfavorite,
    store as favorite,
} from '@/routes/games/favorite';
import {
    destroy as alertOff,
    store as alertOn,
} from '@/routes/games/release-alert';

/**
 * 관심 게임·출시 알림 토글 (REQ-604, REQ-204). 비회원이면 로그인 화면으로 보내고, 로그인 후 지금 화면으로 돌아온다.
 * 처리 후 서버가 보던 화면으로 돌려보내므로 props가 새 상태로 바뀐다.
 */
export function useFavoriteActions() {
    const page = usePage();
    const options = { preserveScroll: true, preserveState: true };

    function goLogin() {
        router.visit(login({ query: { redirect: page.url } }));
    }

    function toggleFavorite(gameId: number, current: boolean | null) {
        if (current === null) {
            return goLogin();
        }

        router.visit(current ? unfavorite(gameId) : favorite(gameId), options);
    }

    function toggleReleaseAlert(gameId: number, current: boolean | null) {
        if (current === null) {
            return goLogin();
        }

        router.visit(current ? alertOff(gameId) : alertOn(gameId), options);
    }

    return { toggleFavorite, toggleReleaseAlert };
}
