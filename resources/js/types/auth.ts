export type User = {
    uuid: string;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    timezone: string;
    locale: string;
};

export type Auth = {
    user: User;
};
