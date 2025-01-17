<?php

namespace BitWasp\Bitcoin\Serializer\Signature;

use BitWasp\Bitcoin\Crypto\EcAdapter\Serializer\Signature\DerSignatureSerializerInterface;
use BitWasp\Bitcoin\Signature\TransactionSignature;
use BitWasp\Buffertools\Parser;

class TransactionSignatureSerializer
{
    /**
     * @var DerSignatureSerializerInterface
     */
    private $sigSerializer;

    /**
     * @param DerSignatureSerializerInterface $sigSerializer
     */
    public function __construct(DerSignatureSerializerInterface $sigSerializer)
    {
        $this->sigSerializer = $sigSerializer;
    }

    /**
     * @param TransactionSignature $txSig
     * @return \BitWasp\Buffertools\Buffer
     */
    public function serialize(TransactionSignature $txSig)
    {
        $sig = $this->sigSerializer->serialize($txSig->getSignature());
        $parser = new Parser($sig->getHex());
        $parser->writeInt(1, $txSig->getHashType());
        $buffer = $parser->getBuffer();
        return $buffer;
    }

    /**
     * @param $string
     * @return TransactionSignature
     */
    public function parse($string)
    {
        $buffer = (new Parser($string))->getBuffer();
        $sig = $buffer->slice(0, $buffer->getSize() - 1);
        $hashType = $buffer->slice(-1);
        return new TransactionSignature(
            $this->sigSerializer->getEcAdapter(),
            $this->sigSerializer->parse($sig),
            $hashType->getInt()
        );
    }
}
