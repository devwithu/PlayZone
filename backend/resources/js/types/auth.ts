// HandleInertiaRequests가 공유하는 로그인 회원 정보 (개인정보 제외)
export type UserStatus = 'pending' | 'active' | 'suspended' | 'withdrawn';

export type User = {
    id: number;
    nickname: string | null;
    status: UserStatus;
    point_balance: number;
    profile_image_url: string | null;
};

export type Auth = {
    user: User;
};
