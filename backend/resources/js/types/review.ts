// 리뷰 화면 props. 서버의 ReviewResource·ReviewCommentResource·GameRatingService::summary()와 같은 모양이다 (api-spec §7.3)
import type { Taxonomy } from '@/types/game';

export type Author = {
    /** 탈퇴한 회원이면 null */
    id: number | null;
    nickname: string;
    level: { name: string; icon_url: string | null } | null;
};

export type ReviewStatus = 'published' | 'blinded' | 'hidden' | 'deleted';

export type Review = {
    id: number;
    game_id: number;
    /** 상세에서만 */
    game?: { id: number; title: string; slug: string };
    author: Author;
    platform: Taxonomy;
    title: string;
    content: string;
    overall_score: number;
    /** 입력한 세부 점수 {slug: score} */
    ratings: Record<string, number>;
    has_spoiler: boolean;
    like_count: number;
    comment_count: number;
    edited_at: string | null;
    created_at: string;
    /** 비회원이면 null */
    is_mine: boolean | null;
    /** 비회원이면 null */
    is_liked: boolean | null;
    /** 본인 리뷰에만 */
    status?: ReviewStatus;
    hidden_reason?: string | null;
};

export type ReviewComment = {
    id: number;
    review_id: number;
    author: Author | null;
    content: string | null;
    status: 'published' | 'hidden' | 'deleted';
    placeholder: string | null;
    is_edited: boolean;
    created_at: string;
    is_mine: boolean | null;
};

export type RatingCategory = { name: string; slug: string };

export type RatingSummary = {
    review_count: number;
    rating_avg: number | null;
    /** 종합 점수 '1'~'10'별 리뷰 수 */
    distribution: Record<string, number>;
    categories: {
        slug: string;
        name: string;
        avg_score: number | null;
        score_count: number;
    }[];
};

export type Pagination = {
    current_page: number;
    last_page: number;
    total: number;
};

export type ReviewSort = 'likes' | 'latest' | 'score_high' | 'score_low';

export const reviewSortLabels: Record<ReviewSort, string> = {
    likes: '추천순',
    latest: '최신순',
    score_high: '점수 높은순',
    score_low: '점수 낮은순',
};

export const reviewStatusLabels: Record<ReviewStatus, string> = {
    published: '공개',
    blinded: '검토 중',
    hidden: '숨김',
    deleted: '삭제',
};

/** ISO 시각(UTC) → 한국 시간 'YYYY.MM.DD'. SSR과 브라우저에서 같은 값을 내도록 시간대를 고정한다 */
export function formatKstDate(iso: string): string {
    const kst = new Date(new Date(iso).getTime() + 9 * 60 * 60 * 1000);
    const pad = (n: number) => String(n).padStart(2, '0');

    return `${kst.getUTCFullYear()}.${pad(kst.getUTCMonth() + 1)}.${pad(kst.getUTCDate())}`;
}
