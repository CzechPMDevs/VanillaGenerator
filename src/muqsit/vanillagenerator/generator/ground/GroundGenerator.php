<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\ground;

use muqsit\vanillagenerator\generator\overworld\biome\BiomeClimateManager;
use pocketmine\block\Block;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use pocketmine\world\format\Chunk;
use function max;

class GroundGenerator{

	protected Block $topMaterial;
	protected Block $groundMaterial;
	protected int $bedrockRoughness = 5;

	public function __construct(?Block $topMaterial = null, ?Block $groundMaterial = null){
		$this->setTopMaterial($topMaterial ?? VanillaBlocks::GRASS());
		$this->setGroundMaterial($groundMaterial ?? VanillaBlocks::DIRT());
	}

	public function getBedrockRoughness() : int{
		return $this->bedrockRoughness;
	}

	public function setBedrockRoughness(int $bedrockRoughness) : void{
		$this->bedrockRoughness = $bedrockRoughness;
	}

	final protected function setTopMaterial(Block $topMaterial) : void{
		$this->topMaterial = $topMaterial;
	}

	final protected function setGroundMaterial(Block $groundMaterial) : void{
		$this->groundMaterial = $groundMaterial;
	}

	/**
	 * Generates a terrain column.
	 *
	 * @param ChunkManager $world the affected world
	 * @param Random $random the PRNG to use
	 * @param int $x the chunk X coordinate
	 * @param int $z the chunk Z coordinate
	 * @param int $biome the biome this column is in
	 * @param float $surfaceNoise the amplitude of random variation in surface height
	 */
	public function generateTerrainColumn(ChunkManager $world, Random $random, int $x, int $z, int $biome, float $surfaceNoise) : void{
		$seaLevel = 64;

		$topMat = $this->topMaterial->getStateId();
		$groundMat = $this->groundMaterial->getStateId();
		$groundMatId = $this->groundMaterial->getTypeId();

		$chunkX = $x;
		$chunkZ = $z;

		$surfaceHeight = max((int) ($surfaceNoise / 3.0 + 3.0 + $random->nextFloat() * 0.25), 1);
		$deep = -1;

		$registry = RuntimeBlockStateRegistry::getInstance();
		$air = VanillaBlocks::AIR()->getStateId();
		$stone = VanillaBlocks::STONE()->getStateId();
		$sandstone = VanillaBlocks::SANDSTONE()->getStateId();
		$gravel = VanillaBlocks::GRAVEL()->getStateId();
		$bedrock = VanillaBlocks::BEDROCK()->getStateId();
		$ice = VanillaBlocks::ICE()->getStateId();

		/** @var Chunk $chunk */
		$chunk = $world->getChunk($x >> 4, $z >> 4);
		$blockX = $x & 0x0f;
		$blockZ = $z & 0x0f;

		for($y = 255; $y >= 0; --$y){
			if($y <= $random->nextBoundedInt($this->bedrockRoughness)){
				$chunk->setBlockStateId($blockX, $y, $blockZ, $bedrock);
			}else{
				$mat = $registry->fromStateId($chunk->getBlockStateId($blockX, $y, $blockZ));
				$matId = $mat->getTypeId();
				if($matId === BlockTypeIds::AIR){
					$deep = -1;
				}elseif($matId === BlockTypeIds::STONE){
					if($deep === -1){
						if($y >= $seaLevel - 5 && $y <= $seaLevel){
							$topMat = $this->topMaterial->getStateId();
							$groundMat = $this->groundMaterial->getStateId();
							$groundMatId = $this->groundMaterial->getTypeId();
						}

						$deep = $surfaceHeight;
						if($y >= $seaLevel - 2){
							$chunk->setBlockStateId($blockX, $y, $blockZ, $topMat);
						}elseif($y < $seaLevel - 8 - $surfaceHeight){
							$topMat = $air;
							$groundMat = $stone;
							$groundMatId = BlockTypeIds::STONE;
							$chunk->setBlockStateId($blockX, $y, $blockZ, $gravel);
						}else{
							$chunk->setBlockStateId($blockX, $y, $blockZ, $groundMat);
						}
					}elseif($deep > 0){
						--$deep;
						$chunk->setBlockStateId($blockX, $y, $blockZ, $groundMat);

						if($deep === 0 && $groundMatId === BlockTypeIds::SAND){
							$deep = $random->nextBoundedInt(4) + max(0, $y - $seaLevel - 1);
							$groundMat = $sandstone;
							$groundMatId = BlockTypeIds::SANDSTONE;
						}
					}
				}elseif($matId === BlockTypeIds::WATER && $y === $seaLevel - 2 && BiomeClimateManager::isCold($biome, $chunkX, $y, $chunkZ)){
					$chunk->setBlockStateId($blockX, $y, $blockZ, $ice);
				}
			}
		}
	}
}
