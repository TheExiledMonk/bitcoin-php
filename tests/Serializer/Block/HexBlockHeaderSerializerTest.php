<?php

namespace BitWasp\Bitcoin\Tests\Serializer\Block;

use BitWasp\Bitcoin\Serializer\Block\BlockHeaderSerializer;
use BitWasp\Bitcoin\Tests\AbstractTestCase;

class HexBlockHeaderSerializerTest extends AbstractTestCase
{
    /**
     * @expectedException \BitWasp\Buffertools\Exceptions\ParserOutOfRange
     */
    public function testInvalidParse()
    {
        $serializer = new BlockHeaderSerializer;
        $serializer->parse('');
    }
}
