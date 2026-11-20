<?php

use App\Domain\Projects\Models\Project;
use App\Domain\Projects\Support\RemoteProject;

function remoteProject(array $attributes = []): RemoteProject
{
    $project = new Project;
    $project->forceFill([
        'slug' => 'shop',
        'deploy_path' => '/var/www/shop',
        'repository_url' => 'git@github.com:acme/shop.git',
        'branch' => 'main',
        'shared_paths' => ['.env', 'storage/'],
        'deploy_private_key' => "-----BEGIN OPENSSH PRIVATE KEY-----
abc
-----END OPENSSH PRIVATE KEY-----",
        ...$attributes,
    ]);

    return new RemoteProject($project);
}

it('checks the deploy path is writable', function () {
    expect(remoteProject()->ensureWritableRootCommand())
        ->toBe("mkdir -p '/var/www/shop' && test -w '/var/www/shop'");
});

it('creates releases, shared and the shared paths', function () {
    expect(remoteProject()->createStructureCommand())->toBe(
        "mkdir -p '/var/www/shop/releases' '/var/www/shop/shared' '/var/www/shop/shared/storage'"
        ." '/var/www/shop/shared/storage/app/public' '/var/www/shop/shared/storage/framework/cache/data'"
        ." '/var/www/shop/shared/storage/framework/sessions' '/var/www/shop/shared/storage/framework/views'"
        ." '/var/www/shop/shared/storage/logs'"
        ." && touch '/var/www/shop/shared/.env'"
    );
});

it('creates parent directories of nested shared files', function () {
    $command = remoteProject(['shared_paths' => ['config/secrets.json']])->createStructureCommand();

    expect($command)->toContain("'/var/www/shop/shared/config'")
        ->toContain("touch '/var/www/shop/shared/config/secrets.json'")
        ->not->toContain('storage');
});

it('verifies repository access with the project deploy key only', function () {
    expect(remoteProject()->verifyRepositoryCommand())->toBe(
        "GIT_SSH_COMMAND='ssh -i \"\$HOME/.ssh/pushpilot_shop\" -o IdentitiesOnly=yes -o StrictHostKeyChecking=accept-new'"
        ." git ls-remote --exit-code --heads 'git@github.com:acme/shop.git' 'refs/heads/main'"
    );
});

it('writes .env atomically through base64 so no content can escape the command', function () {
    $content = "APP_KEY=x'; rm -rf / #\nDB=\$(id)\n";
    $command = remoteProject()->writeEnvCommand($content);

    expect($command)
        ->toContain("printf '%s' '".base64_encode($content)."' | base64 -d > '/var/www/shop/shared/.env.pushpilot-tmp'")
        ->toContain("chmod 640 '/var/www/shop/shared/.env.pushpilot-tmp'")
        ->toEndWith("mv -f '/var/www/shop/shared/.env.pushpilot-tmp' '/var/www/shop/shared/.env'")
        ->not->toContain('rm -rf')
        ->not->toContain('$(id)');
});

it('installs the deploy key with private permissions', function () {
    $command = remoteProject()->installDeployKeyCommand();

    expect($command)
        ->toStartWith('mkdir -p "$HOME/.ssh" && chmod 700 "$HOME/.ssh"')
        ->toContain('umask 077')
        ->toContain('"$HOME"/.ssh/\'pushpilot_shop\'.tmp')
        ->toEndWith('chmod 600 "$HOME"/.ssh/\'pushpilot_shop\'');
});
