<?php
/**
 * Style preset definitions for Prompt_Builder (included once).
 *
 * @package WC_AICC\Config
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return array(
	'original' => array(
		'minimal_transform' => true,
		'core'              => array(
			'Create a polished pet portrait that stays faithful to the uploaded reference image',
			'preserve the pet recognizable facial features, markings, proportions, expression, and overall natural appearance',
			'do not add a costume or fictional character styling',
			'refined portrait finish while keeping the subject close to the reference photo',
		),
		'composition'     => 'portrait composition faithful to the reference framing and pose',
		'negative_extra'  => array(
			'no costume, uniforms, crowns, or character outfits unless present in reference',
			'no replacing the pet with a human or different species',
		),
	),
	'royal' => array(
		'core' => array(
			'Create a regal royal portrait of the pet as a king or queen',
			'elegant ceremonial attire such as crown, tiara, royal robes, or ermine-trimmed cloak',
			'refined classical portrait composition with dignified grandeur',
			'preserve the pet recognizable facial features, markings, and proportions from the reference',
		),
		'composition'    => 'formal ceremonial portrait with regal presence',
		'negative_extra' => array(
			'no human figure replacing the pet subject',
			'no cartoon or flat illustration look',
		),
	),
	'magazine_cover' => array(
		'allows_cover_text' => true,
		'core'              => array(
			'Create an editorial magazine cover portrait of the pet',
			'bold hero portrait with premium typography-friendly layout and intentional masthead space',
			'high-end fashion editorial illustration aesthetic',
			'preserve the pet recognizable facial features, markings, and proportions from the reference',
		),
		'composition'    => 'editorial magazine cover layout with clear headline space above the subject',
		'negative_extra' => array(
			'no cluttered cover lines or barcode',
			'no distorted anatomy',
		),
	),
	'cowboy' => array(
		'core' => array(
			'Create a western cowboy portrait of the pet',
			'western-inspired outfit such as hat, bandana, denim, or leather accents',
			'rustic frontier setting hints with golden hour warmth where appropriate',
			'preserve the pet recognizable facial features, markings, and proportions from the reference',
		),
		'composition'    => 'rugged western portrait composition',
		'negative_extra' => array(
			'no human cowboy replacing the pet subject',
		),
	),
	'firefighter' => array(
		'core' => array(
			'Create a heroic firefighter portrait of the pet',
			'firefighter uniform with helmet and reflective gear details',
			'dramatic courageous setting with warm emergency lighting atmosphere',
			'preserve the pet recognizable facial features, markings, and proportions from the reference',
		),
		'composition'    => 'heroic portrait composition with dramatic lighting',
		'negative_extra' => array(
			'no human firefighter replacing the pet subject',
			'no graphic disaster imagery',
		),
	),
	'astronaut' => array(
		'core' => array(
			'Create an astronaut portrait of the pet in a space suit',
			'cosmic environment with stars, nebula, or space station hints',
			'cinematic sci-fi portrait lighting',
			'preserve the pet recognizable facial features, markings, and proportions from the reference',
		),
		'composition'    => 'cinematic space portrait composition',
		'negative_extra' => array(
			'no human astronaut replacing the pet subject',
		),
	),
	'pirate' => array(
		'core' => array(
			'Create a classic pirate portrait of the pet',
			'pirate outfit with hat, coat, and nautical adventure styling',
			'ocean, ship deck, or coastal setting atmosphere',
			'preserve the pet recognizable facial features, markings, and proportions from the reference',
		),
		'composition'    => 'adventurous nautical portrait composition',
		'negative_extra' => array(
			'no human pirate replacing the pet subject',
			'no violent or gory imagery',
		),
	),
	'gentleman' => array(
		'core' => array(
			'Create a sophisticated gentleman portrait of the pet',
			'formal tailored attire such as suit, bow tie, waistcoat, or pocket square',
			'elegant refined portrait with luxury editorial polish',
			'preserve the pet recognizable facial features, markings, and proportions from the reference',
		),
		'composition'    => 'elegant formal portrait composition',
		'negative_extra' => array(
			'no human gentleman replacing the pet subject',
		),
	),
);
