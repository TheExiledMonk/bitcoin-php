<?php

namespace BitWasp\Bitcoin\Crypto\EcAdapter\Impl\Secp256k1\Key;

use BitWasp\Bitcoin\Crypto\EcAdapter\Impl\Secp256k1\Adapter\EcAdapter;
use BitWasp\Bitcoin\Crypto\EcAdapter\Impl\Secp256k1\Serializer\Key\PublicKeySerializer;
use BitWasp\Bitcoin\Crypto\EcAdapter\Key\Key;
use BitWasp\Bitcoin\Crypto\EcAdapter\Key\PublicKeyInterface;
use BitWasp\Bitcoin\Crypto\EcAdapter\Signature\SignatureInterface;
use BitWasp\Bitcoin\Crypto\Hash;
use BitWasp\Buffertools\Buffer;

class PublicKey extends Key implements PublicKeyInterface
{
    /**
     * @var EcAdapter
     */
    private $ecAdapter;

    /**
     * @var bool|false
     */
    private $compressed;

    /**
     * @var resource
     */
    private $pubkey_t;

    /**
     * @param EcAdapter $ecAdapter
     * @param resource $secp256k1_pubkey_t
     * @param bool|false $compressed
     */
    public function __construct(EcAdapter $ecAdapter, $secp256k1_pubkey_t, $compressed = false)
    {
        if (!is_resource($secp256k1_pubkey_t) ||
            !get_resource_type($secp256k1_pubkey_t) === SECP256K1_TYPE_PUBKEY) {
            throw new \InvalidArgumentException('Secp256k1\Key\PublicKey expects ' . SECP256K1_TYPE_PUBKEY . ' resource');
        }
        if (false === is_bool($compressed)) {
            throw new \InvalidArgumentException('PublicKey: Compressed must be a boolean');
        }
        $this->ecAdapter = $ecAdapter;
        $this->pubkey_t = $secp256k1_pubkey_t;
        $this->compressed = $compressed;
    }

    /**
     * @param Buffer $msg32
     * @param SignatureInterface $signature
     * @return bool
     */
    public function verify(Buffer $msg32, SignatureInterface $signature)
    {
        return $this->ecAdapter->verify($msg32, $this, $signature);
    }

    /**
     * @return bool
     */
    public function isPrivate()
    {
        return false;
    }

    /**
     * @return bool|false
     */
    public function isCompressed()
    {
        return $this->compressed;
    }

    /**
     * @return resource
     */
    public function getResource()
    {
        return $this->pubkey_t;
    }

    /**
     * @return Buffer
     */
    public function getPubKeyHash()
    {
        return Hash::sha256ripe160($this->getBuffer());
    }

    /**
     * @return resource
     * @throws \Exception
     */
    private function clonePubkey()
    {
        $context = $this->ecAdapter->getContext();
        $serialized = '';
        if (1 !== secp256k1_ec_pubkey_serialize($context, $this->pubkey_t, $this->compressed, $serialized)) {
            throw new \Exception('Secp256k1: pubkey serialize');
        }

        $clone = '';
        if (1 !== secp256k1_ec_pubkey_parse($context, $serialized, $clone)) {
            throw new \Exception('Secp256k1 pubkey parse');
        }
        return $clone;
    }

    /**
     * @param int $tweak
     * @return PublicKey
     * @throws \Exception
     */
    public function tweakAdd($tweak)
    {
        $context = $this->ecAdapter->getContext();
        $math = $this->ecAdapter->getMath();
        $bin = pack("H*", str_pad($math->decHex($tweak), 64, '0', STR_PAD_LEFT));

        $clone = $this->clonePubkey();
        if (1 !== secp256k1_ec_pubkey_tweak_add($context, $clone, $bin)) {
            throw new \RuntimeException('Secp256k1: tweak add failed.');
        }

        return new PublicKey($this->ecAdapter, $clone, $this->compressed);
    }

    /**
     * @param int $tweak
     * @return PublicKey
     * @throws \Exception
     */
    public function tweakMul($tweak)
    {
        $context = $this->ecAdapter->getContext();
        $math = $this->ecAdapter->getMath();
        $bin = pack("H*", str_pad($math->decHex($tweak), 64, '0', STR_PAD_LEFT));

        $clone = $this->clonePubkey();
        if (1 !== secp256k1_ec_pubkey_tweak_mul($context, $clone, $bin)) {
            throw new \RuntimeException('Secp256k1: tweak mul failed.');
        }

        return new PublicKey($this->ecAdapter, $clone, $this->compressed);
    }

    /**
     * @return Buffer
     */
    public function getBuffer()
    {
        return (new PublicKeySerializer($this->ecAdapter))->serialize($this);
    }
}
