<?php

namespace BitWasp\Bitcoin\Crypto\EcAdapter\Impl\Secp256k1\Serializer\Key;

use BitWasp\Bitcoin\Crypto\EcAdapter\Impl\Secp256k1\Adapter\EcAdapter;
use BitWasp\Bitcoin\Crypto\EcAdapter\Impl\Secp256k1\Key\PrivateKey;
use BitWasp\Bitcoin\Crypto\EcAdapter\Key\PrivateKeyInterface;
use BitWasp\Bitcoin\Crypto\EcAdapter\Serializer\Key\PrivateKeySerializerInterface;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\Parser;

/**
 * Private Key Serializer - specific to secp256k1
 */
class PrivateKeySerializer implements PrivateKeySerializerInterface
{
    /**
     * @var bool
     */
    private $haveNextCompressed = false;

    /**
     * @var EcAdapter
     */
    private $ecAdapter;

    /**
     * @param EcAdapter $ecAdapter
     */
    public function __construct(EcAdapter $ecAdapter)
    {
        $this->ecAdapter = $ecAdapter;
    }

    /**
     * @param PrivateKey $privateKey
     * @return Buffer
     */
    private function doSerialize(PrivateKey $privateKey)
    {
        return new Buffer($privateKey->getSecretBinary(), 32, $this->ecAdapter->getMath());
    }

    /**
     * @param PrivateKeyInterface $privateKey
     * @return Buffer
     */
    public function serialize(PrivateKeyInterface $privateKey)
    {
        /** @var PrivateKey $privateKey */
        return $this->doSerialize($privateKey);
    }

    /**
     * Tells the serializer the next key to be parsed should be compressed.
     *
     * @return $this
     */
    public function setNextCompressed()
    {
        $this->haveNextCompressed = true;
        return $this;
    }

    /**
     * @param Parser $parser
     * @return PrivateKey
     * @throws \BitWasp\Buffertools\Exceptions\ParserOutOfRange
     */
    public function fromParser(Parser & $parser)
    {
        $compressed = $this->haveNextCompressed;
        $this->haveNextCompressed = false;
        $int = $parser->readBytes(32)->getInt();
        return $this->ecAdapter->getPrivateKey($int, $compressed);
    }

    /**
     * @param $data
     * @return PrivateKey
     */
    public function parse($data)
    {
        return $this->fromParser(new Parser($data, $this->ecAdapter->getMath()));
    }
}
