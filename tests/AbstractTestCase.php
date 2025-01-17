<?php

namespace BitWasp\Bitcoin\Tests;

use BitWasp\Bitcoin\Block\BlockFactory;
use BitWasp\Bitcoin\Crypto\EcAdapter\EcAdapterFactory;
use BitWasp\Bitcoin\Math\Math;
use BitWasp\Bitcoin\Crypto\EcAdapter\PhpEcc;
use BitWasp\Bitcoin\Crypto\EcAdapter\Secp256k1;
use Mdanter\Ecc\EccFactory;

abstract class AbstractTestCase extends \PHPUnit_Framework_TestCase
{

    /**
     * @param $filename
     * @return string
     */
    public function dataFile($filename)
    {
        return file_get_contents(__DIR__ . '/Data/' . $filename);
    }

    /**
     * @return array
     */
    public function getBlocks()
    {
        $blocks = $this->dataFile('180blocks');
        $a = explode("\n", $blocks);
        return array_filter($a, 'strlen');
        return $a;
    }

    /**
     * @param $i
     * @return \BitWasp\Bitcoin\Block\Block
     */
    public function getBlock($i)
    {
        $blocks = $this->getBlocks();
        $hex = $blocks[$i];
        $b = BlockFactory::fromHex($hex);
        return $b;
    }

    /**
     * @return \BitWasp\Bitcoin\Block\Block
     */
    public function getGenesisBlock()
    {
        $b = $this->getBlock(0);
        return $b;
    }

    /**
     * @return array
     */
    public function getEcAdapters()
    {
        $math = new Math;
        $generator = EccFactory::getSecgCurves()->generator256k1();
        $adapters = [];

        if (getenv('TRAVIS_PHP_VERSION')) {
            // If travis
            // If EXT_SECP256K1 env var is set, only return secp256k1.
            // Otherwise return phpecc
            if (strlen(getenv('EXT_SECP256K1')) == 0) {
                $adapters[] = [EcAdapterFactory::getPhpEcc($math, $generator)];
            } else {
                $adapters[] = [EcAdapterFactory::getSecp256k1($math, $generator)];
            }
        } else {
            // Env var was set, just pass secp256k1
            $adapters[] = [(extension_loaded('secp256k1')
                ? EcAdapterFactory::getSecp256k1($math, $generator)
                : EcAdapterFactory::getPhpEcc($math, $generator))];
        }

        return $adapters;
    }

    private static $context;

    public static function getContext()
    {
        if (self::$context == null) {
            self::$context = secp256k1_context_create(SECP256K1_CONTEXT_VERIFY | SECP256K1_CONTEXT_SIGN);
        }

        return self::$context;
    }

    public function safeMath()
    {
        return new Math();
    }

    public function safeGenerator()
    {
        return EccFactory::getSecgCurves($this->safeMath())->generator256k1();
    }

    public function safeEcAdapter()
    {
        $math = $this->safeMath();
        $generator = $this->safeGenerator();
        return extension_loaded('secp256k1') ? EcAdapterFactory::getSecp256k1($math, $generator): new \BitWasp\Bitcoin\Crypto\EcAdapter\Impl\PhpEcc\Adapter\EcAdapter($math, $generator);
    }
}
