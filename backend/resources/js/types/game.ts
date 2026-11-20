// 게임 화면 props. 서버의 App\Http\Resources\Api\V1\* 응답과 같은 모양이다 (api-spec §7.2)

export type Taxonomy = {
    id: number;
    name: string;
    slug: string;
};

export type ReleaseStatus =
    | 'announced'
    | 'scheduled'
    | 'delayed'
    | 'released'
    | 'cancelled';

export type GameListItem = {
    id: number;
    title: string;
    slug: string;
    cover_url: string | null;
    genres: Taxonomy[];
    platforms: Taxonomy[];
    review_count: number;
    rating_avg: number | null;
    is_reviewable: boolean;
    primary_release: {
        release_date: string | null;
        release_status: ReleaseStatus;
        display_date: string;
    } | null;
};

export type ReleaseDate = {
    platform: Taxonomy;
    release_date: string | null;
    date_precision: 'day' | 'month' | 'quarter' | 'year' | 'tba';
    release_status: ReleaseStatus;
    display_date: string;
};

export type GameDetail = Omit<GameListItem, 'primary_release'> & {
    description: string | null;
    developer: string | null;
    publisher: string | null;
    screenshot_urls: string[];
    banner_url: string | null;
    release_dates: ReleaseDate[];
    favorite_count: number;
};

export const releaseStatusLabels: Record<ReleaseStatus, string> = {
    announced: '발표',
    scheduled: '출시 예정',
    delayed: '연기',
    released: '출시',
    cancelled: '취소',
};
