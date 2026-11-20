export type ProjectSummary = {
    id: number;
    name: string;
    slug: string;
    repository_url: string;
    branch: string;
    deploy_path: string;
    server: { id: number; name: string; host: string };
    initialized_at: string | null;
};

export type HookStageValue =
    | 'before_build'
    | 'after_build'
    | 'before_activate'
    | 'after_activate';

export type ProjectHook = {
    stage: HookStageValue;
    command: string;
};

export type ProjectDetails = ProjectSummary & {
    deploy_public_key: string;
    shared_paths: string[];
    run_composer: boolean;
    run_npm_build: boolean;
    run_migrations: boolean;
    queue_restart: boolean;
    php_fpm_service: string | null;
    keep_releases: number;
    health_check_url: string | null;
    health_check_status: number;
    auto_rollback: boolean;
    hooks: ProjectHook[];
    environment: {
        keys: string[];
        synced_at: string | null;
    };
};

export type ActionResultFlash = {
    successful: boolean;
    message: string;
    steps: { label: string; ok: boolean; message: string | null }[];
};
