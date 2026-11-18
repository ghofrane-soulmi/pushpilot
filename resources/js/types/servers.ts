export type ServerStatus = 'unknown' | 'reachable' | 'unreachable';

export type Server = {
    id: number;
    name: string;
    host: string;
    port: number;
    username: string;
    status: ServerStatus;
    last_checked_at: string | null;
    created_at: string | null;
};

export type RequirementCheck = {
    key: string;
    label: string;
    ok: boolean;
    required: boolean;
    version: string | null;
    hint: string | null;
};

export type RequirementsReport = {
    checks: RequirementCheck[];
    php_fpm_service: string | null;
    ready: boolean;
};

export type ServerDetails = Server & {
    public_key: string;
    authorize_command: string;
    requirements: RequirementsReport | null;
};

export type ConnectionTestFlash = {
    successful: boolean;
    message: string;
};
