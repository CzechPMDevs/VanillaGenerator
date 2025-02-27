<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\ground;

use muqsit\vanillagenerator\generator\noise\glowstone\SimplexOctaveGenerator;
use muqsit\vanillagenerator\generator\utils\MathHelper;
use pocketmine\block\BlockFactory;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\RuntimeBlockStateRegistry;
use pocketmine\block\utils\DirtType;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Dye;
use pocketmine\utils\Random;
use pocketmine\world\ChunkManager;
use function abs;
use function array_fill;
use function ceil;
use function count;
use function max;
use function min;
use function round;

class MesaGroundGenerator extends GroundGenerator{

	public const NORMAL = 0;
	public const BRYCE = 1;
	public const FOREST = 2;

	private int $type;

	/** @var array<DyeColor|null> */
	private array $colorLayer;

	private ?SimplexOctaveGenerator $colorNoise = null;
	private ?SimplexOctaveGenerator $canyonHeightNoise = null;
	private ?SimplexOctaveGenerator $canyonScaleNoise = null;
	private ?int $seed = null;

	public function __construct(int $type = self::NORMAL){
		parent::__construct(VanillaBlocks::RED_SAND(), VanillaBlocks::STAINED_CLAY()->setColor(DyeColor::ORANGE()));
		$this->type = $type;
	}

	private function initialize(int $seed) : void{
		if($seed !== $this->seed || $this->colorNoise === null || $this->canyonScaleNoise === null || $this->canyonHeightNoise === null){
			$random = new Random($seed);
			$this->colorNoise = SimplexOctaveGenerator::fromRandomAndOctaves($random, 1, 0, 0, 0);
			$this->colorNoise->setScale(1 / 512.0);
			$this->initializeColorLayers($random);

			$this->canyonHeightNoise = SimplexOctaveGenerator::fromRandomAndOctaves($random, 4, 0, 0, 0);
			$this->canyonHeightNoise->setScale(1 / 4.0);
			$this->canyonScaleNoise = SimplexOctaveGenerator::fromRandomAndOctaves($random, 1, 0, 0, 0);
			$this->canyonScaleNoise->setScale(1 / 512.0);
			$this->seed = $seed;
		}
	}

	public function generateTerrainColumn(ChunkManager $world, Random $random, int $x, int $z, int $biome, float $surfaceNoise) : void{
		$this->initialize($random->getSeed());
		$seaLevel = 64;

		$groundMat = $this->groundMaterial;

		$surfaceHeight = max((int) ($surfaceNoise / 3.0 + 3.0 + $random->nextFloat() * 0.25), 1);
		$colored = MathHelper::getInstance()->cos($surfaceNoise / 3.0 * M_PI) <= 0;
		$bryceCanyonHeight = 0.0;
		if($this->type === self::BRYCE){
			$noiseX = ($x & 0xFFFFFFF0) + ($z & 0xF);
			$noiseZ = ($z & 0xFFFFFFF0) + ($x & 0xF);
			$noiseCanyonHeight = min(abs($surfaceNoise), $this->canyonHeightNoise->noise($noiseX, $noiseZ, 0, 0.5, 2.0, false));
			if($noiseCanyonHeight > 0){
				$heightScale = abs($this->canyonScaleNoise->noise($noiseX, $noiseZ, 0, 0.5, 2.0, false));
				$bryceCanyonHeight = ($noiseCanyonHeight ** 2) * 2.5;
				$maxHeight = ceil(50 * $heightScale) + 14;
				if($bryceCanyonHeight > $maxHeight){
					$bryceCanyonHeight = $maxHeight;
				}
				$bryceCanyonHeight += $seaLevel;
			}
		}

		$chunkX = $x;
		$chunkZ = $z;

		$deep = -1;
		$groundSet = false;

		$grass = VanillaBlocks::GRASS();
		$coarseDirt = VanillaBlocks::DIRT()->setDirtType(DirtType::COARSE());

		for($y = 255; $y >= 0; --$y){
			if($y < (int) $bryceCanyonHeight && $world->getBlockAt($x, $y, $z)->getTypeId() === BlockTypeIds::AIR){
				$world->setBlockAt($x, $y, $z, VanillaBlocks::STONE());
			}
			if($y <= $random->nextBoundedInt(5)){
				$world->setBlockAt($x, $y, $z, VanillaBlocks::BEDROCK());
			}else{
				$matId = $world->getBlockAt($x, $y, $z)->getTypeId();
				if($matId === BlockTypeIds::AIR){
					$deep = -1;
				}elseif($matId === BlockTypeIds::STONE){
					if($deep === -1){
						$groundSet = false;
						if($y >= $seaLevel - 5 && $y <= $seaLevel){
							$groundMat = $this->groundMaterial;
						}

						$deep = $surfaceHeight + max(0, $y - $seaLevel - 1);
						if($y >= $seaLevel - 2){
							if($this->type === self::FOREST && $y > $seaLevel + 22 + ($surfaceHeight << 1)){
								$world->setBlockAt($x, $y, $z, $colored ? $grass : $coarseDirt);
							}elseif($y > $seaLevel + 2 + $surfaceHeight){
								$color = $this->colorLayer[($y + (int) round(
										$this->colorNoise->noise($chunkX, $chunkZ, 0, 0.5, 2.0, false) * 2.0))
								% count($this->colorLayer)];
								$this->setColoredGroundLayer($world, $x, $y, $z, $y < $seaLevel || $y > 128 ? 1 : ($colored ? $color : -1));
							}else{
								$world->setBlockAt($x, $y, $z, $this->topMaterial);
								$groundSet = true;
							}
						}else{
							$world->setBlockAt($x, $y, $z, $groundMat);
						}
					}elseif($deep > 0){
						--$deep;
						if($groundSet){
							$world->setBlockAt($x, $y, $z, $this->groundMaterial);
						}else{
							$color = $this->colorLayer[($y + (int) round(
									$this->colorNoise->noise($chunkX, $chunkZ, 0, 0.5, 2.0, false) * 2.0))
							% count($this->colorLayer)];
							$this->setColoredGroundLayer($world, $x, $y, $z, $color);
						}
					}
				}
			}
		}
	}

	private function setColoredGroundLayer(ChunkManager $world, int $x, int $y, int $z, DyeColor $color) : void{
		$world->setBlockAt($x, $y, $z, $color >= 0 ? VanillaBlocks::STAINED_CLAY()->setColor($color) : VanillaBlocks::HARDENED_CLAY());
	}

	private function setRandomLayerColor(Random $random, int $minLayerCount, int $minLayerHeight, DyeColor $color) : void{
		for($i = 0; $i < $random->nextBoundedInt(4) + $minLayerCount; ++$i){
			$j = $random->nextBoundedInt(count($this->colorLayer));
			$k = 0;
			while($k < $random->nextBoundedInt(3) + $minLayerHeight && $j < count($this->colorLayer)){
				$this->colorLayer[$j++] = $color;
				++$k;
			}
		}
	}

	private function initializeColorLayers(Random $random) : void{
		$this->colorLayer = array_fill(0, 64, null);
		$i = 0;
		while($i < count($this->colorLayer)){
			$i += $random->nextBoundedInt(5) + 1;
			if($i < count($this->colorLayer)){
				$this->colorLayer[$i++] = DyeColor::ORANGE();
			}
		}
		$this->setRandomLayerColor($random, 2, 1, DyeColor::YELLOW());
		$this->setRandomLayerColor($random, 2, 2, DyeColor::BROWN());
		$this->setRandomLayerColor($random, 2, 1, DyeColor::RED());
		$j = 0;
		for($i = 0; $i < $random->nextBoundedInt(3) + 3; ++$i){
			$j += $random->nextBoundedInt(16) + 4;
			if($j >= count($this->colorLayer)){
				break;
			}
			if(($random->nextBoundedInt(2) === 0) || (($j < count($this->colorLayer) - 1) && ($random->nextBoundedInt(2) === 0))){
				$this->colorLayer[$j - 1] = DyeColor::LIGHT_GRAY();
			}else{
				$this->colorLayer[$j] = DyeColor::WHITE();
			}
		}
	}
}
