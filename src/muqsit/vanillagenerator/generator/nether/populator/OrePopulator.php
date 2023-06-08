<?php

declare(strict_types=1);

namespace muqsit\vanillagenerator\generator\nether\populator;

use muqsit\vanillagenerator\generator\object\OreType;
use muqsit\vanillagenerator\generator\overworld\populator\biome\OrePopulator as OverworldOrePopulator;
use pocketmine\block\BlockTypeIds;
use pocketmine\block\VanillaBlocks;
use pocketmine\world\World;

class OrePopulator extends OverworldOrePopulator{

	/**
	 * @noinspection MagicMethodsValidityInspection
	 * @noinspection PhpMissingParentConstructorInspection
	 */
	public function __construct(int $worldHeight = World::Y_MAX){
		$this->addOre(new OreType(VanillaBlocks::NETHER_QUARTZ_ORE(), 10, $worldHeight - (10 * ($worldHeight >> 7)), 13, BlockTypeIds::NETHERRACK), 16);
		$this->addOre(new OreType(VanillaBlocks::MAGMA(), 26, 32 + (5 * ($worldHeight >> 7)), 32, BlockTypeIds::NETHERRACK), 16);
	}
}
