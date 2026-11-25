export type DeploymentStatus =
    | 'queued'
    | 'running'
    | 'succeeded'
    | 'failed'
    | 'cancelled'
    | 'rolled_back';

export type Deployment = {
    id: number;
    type: 'deploy' | 'rollback';
    trigger: 'manual' | 'webhook';
    status: DeploymentStatus;
    commit_hash: string | null;
    commit_message: string | null;
    commit_author: string | null;
    release_name: string | null;
    current_step: string | null;
    user: string | null;
    created_at: string | null;
    started_at: string | null;
    finished_at: string | null;
    duration: number | null;
};

export type DeploymentDetails = Deployment & {
    error_message: string | null;
    warnings: string[];
    cancel_requested: boolean;
    project: { id: number; name: string; branch: string };
    steps: { name: string; label: string; ran: boolean }[];
    log: {
        id: number;
        step: string;
        line: string;
        stream: 'stdout' | 'stderr';
    }[];
};

export function isActive(status: DeploymentStatus): boolean {
    return status === 'queued' || status === 'running';
}

export function formatDuration(seconds: number | null): string {
    if (seconds === null) {
        return '–';
    }

    const minutes = Math.floor(seconds / 60);

    return minutes > 0 ? `${minutes}m ${seconds % 60}s` : `${seconds}s`;
}
