/** Gemeinsame Felder aller gespeicherten Datensätze (JSON heute, PostgreSQL später). */
export type StoredRecord = {
    id: string;
    created_at: string;
    updated_at: string;
};

export type Options = {
    roles: Record<string, string>;
    personTypes: Record<string, string>;
    schools: Record<string, string>;
    audiences: Record<string, string>;
    announcementCategories: Record<string, string>;
    eventCategories: Record<string, string>;
    documentCategories: Record<string, string>;
    guiStatuses: Record<string, string>;
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
    person_type?: string | null;
    class_name?: string | null;
    device_inventory?: string | null;
    /** Anzahl fälliger Abgleiche mit Zielsystemen (nur Tabelle) */
    sync_pending?: number;
    // Weitere Felder aus config/intranet.php → schema.users (Admin-Felder nur für die Administration)
    title?: string | null;
    first_name?: string | null;
    last_name?: string | null;
    short?: string | null;
    department?: string | null;
    escola_roles?: string | null;
    entry_date?: string | null;
    exit_date?: string | null;
    phone2?: string | null;
    mobile?: string | null;
    office_street?: string | null;
    office_zip?: string | null;
    office_city?: string | null;
    private_email?: string | null;
    private_phone?: string | null;
    private_mobile?: string | null;
    private_street?: string | null;
    private_zip?: string | null;
    private_city?: string | null;
    emergency_name?: string | null;
    emergency_phone?: string | null;
    username?: string | null;
    escola_id?: string | null;
    external_id?: string | null;
    notes?: string | null;
};

/** Feld einer Person (aus dem Schema, vom Server geliefert). */
export type PersonField = {
    key: keyof Person;
    label: string;
    type: 'string' | 'text' | 'email' | 'bool' | 'date' | 'enum';
    group: string;
    required: boolean;
    computed: boolean;
    admin: boolean;
    input: 'tel' | null;
    options: Record<string, string> | null;
};

export type PersonGroup = {
    key: string;
    label: string;
    description: string;
    admin: boolean;
    fields: PersonField[];
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
    /** Gruppe in Formular/Detailansicht (nur Personen) */
    group: string | null;
    admin_only: boolean;
    computed: boolean;
};

export type SchemaCollection = {
    name: string;
    label: string;
    description: string | null;
    file: string;
    exists: boolean;
    /** Name des Moduls, null = Core */
    module?: string | null;
    count: number;
    fields: SchemaField[];
    unknown_fields: Record<string, number>;
};
