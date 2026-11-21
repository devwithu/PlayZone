// 커뮤니티 화면 props. 서버의 CommunityController가 만드는 모양이다 (화면 설계 W-09~W-12)
import type { Author } from '@/types/review';

export type Board = {
    id: number;
    name: string;
    slug: string;
    description: string | null;
};

export type PostListItem = {
    id: number;
    board: { name: string; slug: string };
    title: string;
    author: Author;
    view_count: number;
    like_count: number;
    comment_count: number;
    created_at: string;
};

export type PostImage = { id: number; url: string; name: string };

export type PostDetail = PostListItem & {
    content: string;
    images: PostImage[];
    edited_at: string | null;
    is_mine: boolean | null;
    is_liked: boolean | null;
    /** 본인 글에만 */
    status: 'published' | 'blinded' | 'hidden' | null;
    hidden_reason: string | null;
};

export type PostComment = {
    id: number;
    author: Author | null;
    content: string | null;
    status: 'published' | 'hidden' | 'deleted';
    placeholder: string | null;
    is_edited: boolean;
    created_at: string;
    is_mine: boolean | null;
};
