<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\ground;

use pocketmine\block\VanillaBlocks;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;

class GravelPatchGroundGenerator extends GroundGenerator{

	public function generateTerrainColumn(ChunkManager $world, Random $random, int $x, int $z, int $biome, float $surfaceNoise) : void{
		if($surfaceNoise < -1.0 || $surfaceNoise > 2.0){
			$this->setTopMaterial(VanillaBlocks::GRAVEL());
			$this->setGroundMaterial(VanillaBlocks::GRAVEL());
		}else{
			$this->setTopMaterial(VanillaBlocks::GRASS());
			$this->setGroundMaterial(VanillaBlocks::DIRT());
		}

		parent::generateTerrainColumn($world, $random, $x, $z, $biome, $surfaceNoise);
	}
}
