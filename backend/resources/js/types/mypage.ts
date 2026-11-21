// 마이페이지 화면 props. 서버의 MyPageController가 만드는 모양이다 (화면 설계 W-17)

export type MyPageHeader = {
    nickname: string;
    point_balance: number;
    lifetime_points: number;
    level: {
        id: number;
        name: string;
        min_points: number;
        icon_url: string | null;
    };
    next_level: {
        id: number;
        name: string;
        min_points: number;
        points_needed: number;
    } | null;
};

export type PointTransaction = {
    id: number;
    type: string;
    type_label: string;
    amount: number;
    balance_after: number;
    description: string | null;
    created_at: string;
};

export type MyPost = {
    id: number;
    board: { name: string; slug: string; is_active: boolean };
    title: string;
    status: 'published' | 'blinded' | 'hidden';
    hidden_reason: string | null;
    view_count: number;
    like_count: number;
    comment_count: number;
    created_at: string;
};
