export type PluginField = {
    key: string;
    type:
        | 'text'
        | 'url'
        | 'password'
        | 'select'
        | 'check'
        | 'number'
        | 'textarea'
        | 'file';
    label: string;
    /** Nur «file»: erlaubte Endungen, z. B. ".xlsx,.csv" */
    accept?: string;
    help?: string;
    required?: boolean;
    default?: string | number | boolean | null;
    choices?: [string, string][];
    placeholder?: string;
};

export type PluginEndpointParam = {
    name: string;
    default?: string | number | null;
    required?: boolean;
    example?: string;
    help?: string;
};

export type PluginEndpoint = {
    id: string;
    method: string;
    path: string;
    label: string;
    status?: 'full' | 'partial' | 'planned';
    use?: string;
    note?: string;
    permission?: string;
    danger?: boolean;
    params?: PluginEndpointParam[];
    body?: Record<string, unknown>;
};

export type PluginAction = {
    id: string;
    label: string;
    kind: 'read' | 'import' | 'write';
    description?: string;
    run_label?: string;
    confirm?: string;
    options?: PluginField[];
};

export type PluginRun = {
    id: string;
    created_at: string;
    label: string;
    status: 'success' | 'warning' | 'error';
    message: string;
    log: string[];
    user_name: string | null;
    ms: number;
};

export type PluginInfo = {
    id: string;
    name: string;
    version: string;
    category: string;
    description: string;
    author: string | null;
    data_notice: string | null;
    /** Keine offizielle Schnittstelle des Herstellers (z. B. LMVZ) */
    unofficial: boolean;
    service: {
        name: string;
        home: string | null;
        docs: string | null;
        auth: string;
        auth_note: string | null;
        endpoints: PluginEndpoint[];
    };
    settings: PluginField[];
    actions: PluginAction[];
    widgets: { id: string; label: string }[];
    events: string[];
    enabled: boolean;
    configured: boolean;
    allow_write: boolean;
    last_run?: PluginRun | null;
};

export type PluginResult = {
    status: 'success' | 'warning' | 'error';
    message: string;
    ok?: boolean;
    columns?: { key: string; label: string }[];
    rows?: Record<string, unknown>[];
    preview?: boolean;
    log?: string[];
    http_status?: number;
    ms?: number;
    headers?: Record<string, string>;
    body?: string;
};

export type PluginWidget = {
    plugin: string;
    plugin_name: string;
    id: string;
    label: string;
    items: { label: string; value: string | number; hint?: string }[];
    note?: string;
    error: string | null;
};
