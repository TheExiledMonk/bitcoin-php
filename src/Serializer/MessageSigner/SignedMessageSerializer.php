<?php

namespace BitWasp\Bitcoin\Serializer\MessageSigner;

use BitWasp\Bitcoin\Crypto\EcAdapter\Serializer\Signature\CompactSignatureSerializerInterface;
use BitWasp\Bitcoin\MessageSigner\SignedMessage;
use BitWasp\Buffertools\Buffer;

class SignedMessageSerializer
{

    // Message headers
    const HEADER = '-----BEGIN BITCOIN SIGNED MESSAGE-----';
    const SIG_START = '-----BEGIN SIGNATURE-----';
    const FOOTER = '-----END BITCOIN SIGNED MESSAGE-----';

    /**
     * @var CompactSignatureSerializerInterface
     */
    private $csSerializer;

    /**
     * @param CompactSignatureSerializerInterface $csSerializer
     */
    public function __construct(CompactSignatureSerializerInterface $csSerializer)
    {
        $this->csSerializer = $csSerializer;
    }

    /**
     * @param SignedMessage $signedMessage
     * @return string
     */
    public function serialize(SignedMessage $signedMessage)
    {
        $content = self::HEADER . PHP_EOL
            . $signedMessage->getMessage() . PHP_EOL
            . self::SIG_START . PHP_EOL
            . base64_encode($signedMessage->getCompactSignature()->getBinary()) . PHP_EOL
            . self::FOOTER;

        return new Buffer($content);
    }

    /**
     * @param $content
     * @return SignedMessage
     */
    public function parse($content)
    {
        if (0 !== strpos($content, self::HEADER)) {
            throw new \RuntimeException('Message must begin with -----BEGIN BITCOIN SIGNED MESSAGE-----');
        }

        $sigHeaderPos = strpos($content, self::SIG_START);
        if (false === $sigHeaderPos) {
            throw new \RuntimeException('Unable to find start of signature');
        }

        $sigEnd = strlen($content) - strlen(self::FOOTER);
        if (strpos($content, self::FOOTER) !== $sigEnd) {
            throw new \RuntimeException('Message must end with -----END BITCOIN SIGNED MESSAGE-----');
        }

        $messageStartPos = strlen(self::HEADER) + 1;
        $messageEndPos = $sigHeaderPos - $messageStartPos - 1;
        $message = substr($content, $messageStartPos, $messageEndPos);

        $sigStart = $sigHeaderPos + strlen(self::SIG_START);

        $sig = trim(substr($content, $sigStart, $sigEnd - $sigStart));
        $sigHex = bin2hex(base64_decode($sig));
        $compactSig = $this->csSerializer->parse($sigHex);

        return new SignedMessage($message, $compactSig);
    }
}
