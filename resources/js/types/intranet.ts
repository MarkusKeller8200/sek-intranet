/** Gemeinsame Felder aller gespeicherten Datensätze (JSON heute, PostgreSQL später). */
export type StoredRecord = {
    id: string;
    created_at: string;
    updated_at: string;
};

export type Options = {
    roles: Record<string, string>;
    schools: Record<string, string>;
    audiences: Record<string, string>;
    announcementCategories: Record<string, string>;
    eventCategories: Record<string, string>;
    documentCategories: Record<string, string>;
    /** config/intranet.php → colors: Liste → Wert → Farbname */
    colors: Record<string, Record<string, string>>;
};

export type Announcement = StoredRecord & {
    title: string;
    body: string;
    category: string;
    audience: string;
    pinned: boolean;
    published_at: string;
    author_id: string;
    author_name?: string | null;
    scheduled?: boolean;
};

export type IntranetEvent = StoredRecord & {
    title: string;
    description: string | null;
    category: string;
    school: string;
    location: string | null;
    starts_at: string;
    ends_at: string | null;
    all_day: boolean;
    created_by: string;
};

export type DocumentItem = StoredRecord & {
    title: string;
    description: string | null;
    category: string;
    kind: 'file' | 'link';
    url: string | null;
    file_name: string | null;
    file_size: number | null;
    created_by: string;
    created_by_name?: string | null;
};

export type Person = StoredRecord & {
    name: string;
    email: string;
    role: string;
    function: string | null;
    school: string;
    phone: string | null;
    active: boolean;
};

export type SchemaField = {
    name: string;
    label: string;
    type: string;
    required: boolean;
    unique: boolean;
    references: string | null;
    values: string[];
    postgres: string;
    filled: number;
};

export type SchemaCollection = {
    name: string;
    label: string;
    description: string | null;
    file: string;
    exists: boolean;
    count: number;
    fields: SchemaField[];
    unknown_fields: Record<string, number>;
};
