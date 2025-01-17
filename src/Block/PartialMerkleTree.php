<?php

namespace BitWasp\Bitcoin\Block;

use BitWasp\Bitcoin\Crypto\Hash;
use BitWasp\Bitcoin\Serializable;
use BitWasp\Bitcoin\Serializer\Block\PartialMerkleTreeSerializer;
use BitWasp\Buffertools\Buffer;
use BitWasp\Buffertools\Buffertools;

class PartialMerkleTree extends Serializable
{
    /**
     * @var int
     */
    private $elementCount;

    /**
     * @var Buffer[]
     */
    private $vHashes = [];

    /**
     * @var array
     */
    private $vFlagBits = [];

    /**
     * @var bool
     */
    private $fBad = false;

    /**
     * Takes array of hashes and flag array only. Use PartialMerkleTree::create() instead of creating instance directly..
     *
     * @param int $txCount
     * @param array $vHashes
     * @param array $vBits
     */
    public function __construct($txCount = 0, array $vHashes = [], array $vBits = [])
    {
        $this->elementCount = $txCount;
        $this->vHashes = $vHashes;
        $this->vFlagBits = $vBits;
    }

    /**
     * Construct the Merkle tree
     *
     * @param int $txCount
     * @param array $vTxHashes
     * @param array $vMatch
     * @return PartialMerkleTree
     */
    public static function create($txCount, array $vTxHashes, array $vMatch)
    {
        $tree = new self($txCount);
        $tree->traverseAndBuild($tree->calcTreeHeight(), 0, $vTxHashes, $vMatch);
        return $tree;
    }

    /**
     * Calculate tree width for a given height.
     *
     * @param int $height
     * @return int
     */
    public function calcTreeWidth($height)
    {
        return ($this->elementCount + (1 << $height) - 1) >> $height;
    }

    /**
     * Calculate the tree height.
     *
     * @return int
     */
    public function calcTreeHeight()
    {
        $height = 0;
        while ($this->calcTreeWidth($height) > 1) {
            $height++;
        }

        return $height;
    }

    /**
     * @return int
     */
    public function getTxCount()
    {
        return $this->elementCount;
    }

    /**
     * @return Buffer[]
     */
    public function getHashes()
    {
        return $this->vHashes;
    }

    /**
     * @return array
     */
    public function getFlagBits()
    {
        return $this->vFlagBits;
    }

    /**
     * Calculate the hash for the given $height and $position
     *
     * @param int $height
     * @param int $position
     * @param \BitWasp\Buffertools\Buffer[] $vTxid
     * @return \BitWasp\Buffertools\Buffer
     */
    public function calculateHash($height, $position, array $vTxid)
    {

        if ($height == 0) {
            return $vTxid[$position];
        } else {
            $left = $this->calculateHash($height - 1, $position * 2, $vTxid);
            if (($position * 2 + 1) < $this->calcTreeWidth($height - 1)) {
                $right = $this->calculateHash($height - 1, ($position * 2 + 1), $vTxid);
            } else {
                $right = $left;
            }

            $hash = Hash::sha256d(Buffertools::concat($left, $right));
            return $hash;
        }
    }

    /**
     * Construct the list of Merkle Tree hashes
     *
     * @param int $height
     * @param int $position
     * @param array $vTxid - array of Txid's in the block
     * @param array $vMatch - reference to array to populate
     */
    public function traverseAndBuild($height, $position, array $vTxid, array &$vMatch)
    {
        $parent = false;
        for ($p = $position << $height; $p < ($position + 1) << $height && $p < $this->elementCount; $p++) {
            $parent |= $vMatch[$p];
        }

        $this->vFlagBits[] = $parent;

        if (0 == $height || !$parent) {
            $this->vHashes[] = $this->calculateHash($height, $position, $vTxid);
        } else {
            $this->traverseAndBuild($height - 1, $position * 2, $vTxid, $vMatch);
            if (($position * 2 + 1) < $this->calcTreeWidth($height - 1)) {
                $this->traverseAndBuild($height - 1, $position * 2 + 1, $vTxid, $vMatch);
            }
        }
    }

    /**
     * Traverse the Merkle Tree hashes and extract those which have a matching bit.
     *
     * @param int $height
     * @param int $position
     * @param int $nBitsUsed
     * @param int $nHashUsed
     * @param Buffer[] $vMatch
     * @return Buffer
     */
    public function traverseAndExtract($height, $position, &$nBitsUsed, &$nHashUsed, &$vMatch)
    {
        if ($nBitsUsed >= count($this->vFlagBits)) {
            $this->fBad = true;
            return new Buffer();
        }

        $parent = $this->vFlagBits[$nBitsUsed++];
        if (0 == $height || !$parent) {
            if ($nHashUsed >= count($this->vHashes)) {
                $this->fBad = true;
                return new Buffer();
            }
            $hash = $this->vHashes[$nHashUsed++];
            if ($height == 0 && $parent) {
                $vMatch[] = $hash->flip();
            }
            return $hash;
        } else {
            $left = $this->traverseAndExtract($height - 1, $position * 2, $nBitsUsed, $nHashUsed, $vMatch);
            if (($position * 2 + 1) < $this->calcTreeWidth($height - 1)) {
                $right = $this->traverseAndExtract($height - 1, ($position * 2 + 1), $nBitsUsed, $nHashUsed, $vMatch);
                if ($right == $left) {
                    $this->fBad = true;
                }
            } else {
                $right = $left;
            }

            return Hash::sha256d(Buffertools::concat($left, $right));
        }
    }

    /**
     * Extract matches from the tree into provided $vMatch reference.
     *
     * @param Buffer[] $vMatch - reference to array of extracted 'matching' hashes
     * @return Buffer - this will be the merkle root
     * @throws \Exception
     */
    public function extractMatches(array &$vMatch)
    {
        $nTx = $this->getTxCount();
        if (0 == $nTx) {
            throw new \Exception('ntx = 0');
        }

        if ($nTx > BlockInterface::MAX_BLOCK_SIZE / 60) {
            throw new \Exception('ntx > bound size');
        }

        if (count($this->vHashes) > $nTx) {
            throw new \Exception('nHashes > nTx');
        }

        if (count($this->vFlagBits) < count($this->vHashes)) {
            throw new \Exception('nBits < nHashes');
        }

        $height = $this->calcTreeHeight();
        $nBitsUsed = 0;
        $nHashesUsed = 0;
        $merkleRoot = $this->traverseAndExtract($height, 0, $nBitsUsed, $nHashesUsed, $vMatch);
        $merkleRoot = $merkleRoot->flip();
        if ($this->fBad) {
            throw new \Exception('bad data');
        }

        if (($nBitsUsed + 7)/8 != (count($this->vFlagBits)+7)/8) {
            throw new \Exception('Not all bits consumed');
        }

        if ($nHashesUsed !== count($this->vHashes)) {
            throw new \Exception('Not al hashes consumed');
        }

        return $merkleRoot;
    }

    /**
     * @return Buffer
     */
    public function getBuffer()
    {
        return (new PartialMerkleTreeSerializer())->serialize($this);
    }
}
