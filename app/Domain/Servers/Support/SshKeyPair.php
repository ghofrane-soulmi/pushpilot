<?php

namespace App\Domain\Servers\Support;

use phpseclib3\Crypt\EC;
use phpseclib3\Crypt\EC\PrivateKey;
use SensitiveParameter;

/**
 * An ed25519 key pair in OpenSSH format (SRV-2).
 */
final readonly class SshKeyPair
{
    private function __construct(
        #[SensitiveParameter] public string $privateKey,
        public string $publicKey,
    ) {}

    public static function generate(string $comment = 'pushpilot'): self
    {
        /** @var PrivateKey $key */
        $key = EC::createKey('Ed25519');

        return new self(
            $key->toString('OpenSSH'),
            trim($key->getPublicKey()->toString('OpenSSH', ['comment' => $comment])),
        );
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['privateKey' => '[redacted]', 'publicKey' => $this->publicKey];
    }
}
