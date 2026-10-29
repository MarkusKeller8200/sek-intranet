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

export type Auth = {
    user: User;
    can: {
        manageContent: boolean;
        manageUsers: boolean;
    };
};
