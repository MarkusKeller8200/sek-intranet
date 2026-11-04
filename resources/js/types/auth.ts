export type Role = 'admin' | 'redaktion' | 'mitarbeitende';

export type User = {
    id: string;
    name: string;
    email: string;
    role: Role;
    function?: string | null;
    school?: string | null;
    avatar?: string;
};

export type NotificationItem = {
    id: string;
    kind: string;
    title: string;
    body: string;
    href: string;
    read_at: string | null;
    sent_at: string;
};

export type NotificationSummary = {
    unread: number;
    latest: NotificationItem[];
};

export type Auth = {
    user: User;
    can: {
        manageContent: boolean;
        manageUsers: boolean;
        viewGlossary: boolean;
    };
    notifications?: NotificationSummary | null;
};
