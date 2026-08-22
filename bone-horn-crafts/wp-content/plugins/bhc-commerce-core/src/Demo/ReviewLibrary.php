<?php
/**
 * Fictional demo reviews and customers.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Demo;

defined( 'ABSPATH' ) || exit;

/**
 * Review and customer text for the demo dataset.
 *
 * Every reviewer, order and comment below is invented for this reference
 * build. Names are fictional, the wording was written for the demo, and no real
 * customer feedback is reproduced. The seeder marks each review with a
 * `_bhc_demo` meta flag so demo content can always be told apart from real
 * reviews — and so `wp bhc demo reset` can remove exactly what it created.
 */
final class ReviewLibrary {

	/**
	 * Fictional reviewer identities.
	 *
	 * @return array<int, array{name:string, email:string, country:string}>
	 */
	public static function reviewers(): array {
		return [
			[
				'name'    => 'Marcus Hillard',
				'email'   => 'marcus.hillard@example.com',
				'country' => 'US',
			],
			[
				'name'    => 'Priya Raghavan',
				'email'   => 'priya.raghavan@example.com',
				'country' => 'IN',
			],
			[
				'name'    => 'Tomas Lindqvist',
				'email'   => 'tomas.lindqvist@example.com',
				'country' => 'SE',
			],
			[
				'name'    => 'Ellen Roache',
				'email'   => 'ellen.roache@example.com',
				'country' => 'GB',
			],
			[
				'name'    => 'Daniel Okafor',
				'email'   => 'daniel.okafor@example.com',
				'country' => 'US',
			],
			[
				'name'    => 'Sophie Bertrand',
				'email'   => 'sophie.bertrand@example.com',
				'country' => 'FR',
			],
			[
				'name'    => 'Callum Reid',
				'email'   => 'callum.reid@example.com',
				'country' => 'AU',
			],
			[
				'name'    => 'Ana Molnar',
				'email'   => 'ana.molnar@example.com',
				'country' => 'DE',
			],
			[
				'name'    => 'Jesse Vandermeer',
				'email'   => 'jesse.vandermeer@example.com',
				'country' => 'CA',
			],
			[
				'name'    => 'Rina Takahashi',
				'email'   => 'rina.takahashi@example.com',
				'country' => 'JP',
			],
			[
				'name'    => 'Owen Pritchard',
				'email'   => 'owen.pritchard@example.com',
				'country' => 'GB',
			],
			[
				'name'    => 'Isabel Duarte',
				'email'   => 'isabel.duarte@example.com',
				'country' => 'ES',
			],
		];
	}

	/**
	 * Review bodies keyed by material family.
	 *
	 * @return array<string, array<int, array{rating:int, body:string}>>
	 */
	public static function bodies(): array {
		return [
			'bone'    => [
				[
					'rating' => 5,
					'body'   => 'Flat out of the packet — I checked both faces on a granite plate and neither pair needed truing before glue-up. That saves me twenty minutes a handle.',
				],
				[
					'rating' => 5,
					'body'   => 'Third order. The colour is consistent enough that I can quote a customer from a photograph of the last build, which was not true of my previous supplier.',
				],
				[
					'rating' => 4,
					'body'   => 'Good dense material and no smell when sanding. One scale had a small pit near the edge, but it fell inside the handle outline so it made no difference.',
				],
				[
					'rating' => 5,
					'body'   => 'Drilled clean with a brad point at 3mm, no chipping on the exit side. That is the test I care about with bone.',
				],
				[
					'rating' => 4,
					'body'   => 'Took the amber dye job well. Slightly thicker than the listed 0.375 which was fine for me, but worth knowing if you are working to a tight stack.',
				],
				[
					'rating' => 5,
					'body'   => 'Bought the seconds pack to practise jigging. Honestly graded — the flaws are exactly where the listing says they will be, and it still cuts like first-grade material.',
				],
			],
			'horn'    => [
				[
					'rating' => 5,
					'body'   => 'Deep black right through, no grey streak when I ground it down to final thickness. Polished to a mirror with nothing but micromesh and a cotton wheel.',
				],
				[
					'rating' => 5,
					'body'   => 'The bark scales are the real thing. Ridges are irregular in a way you cannot fake, and the inside face was flat enough to glue straight down.',
				],
				[
					'rating' => 4,
					'body'   => 'Beautiful marbling. Horn is horn, so it moved a fraction after shaping and I had to re-flatten before pinning — expected, not a complaint.',
				],
				[
					'rating' => 5,
					'body'   => 'Rams horn arrived with the ripple running the full length of both scales. I have paid more elsewhere for less figure.',
				],
				[
					'rating' => 5,
					'body'   => 'Ordered the mixed pack for a run of five knives and every pair was usable. No filler, no voids opening up mid-grind.',
				],
				[
					'rating' => 4,
					'body'   => 'Nut blanks slot smoothly and hold their edges. Slightly softer than bone under the file, which I actually prefer for a fretless setup.',
				],
			],
			'wood'    => [
				[
					'rating' => 5,
					'body'   => 'Stabilizing is properly done — I cut an offcut in half to check and the resin goes right through the section, not just the surface.',
				],
				[
					'rating' => 5,
					'body'   => 'Buckeye took the shaping without a single tear-out in the soft eyes. Finished at 800 grit and buffed, no sealer needed.',
				],
				[
					'rating' => 4,
					'body'   => 'Rosewood was straight-grained and well seasoned. A little dustier than I expected, so run extraction.',
				],
				[
					'rating' => 5,
					'body'   => 'Sampler pack was the right call. I now know I get on with walnut and not with ebony, which was worth the price on its own.',
				],
			],
			'acrylic' => [
				[
					'rating' => 4,
					'body'   => 'Polishes to a proper gloss with plastic compound. Needs slow passes — I smeared the first one going too fast, which is on me.',
				],
				[
					'rating' => 5,
					'body'   => 'Used the ivory alternative on a restoration where the original handle was cracked. Under a lamp it reads as aged material, not plastic.',
				],
				[
					'rating' => 5,
					'body'   => 'Waterproof and stable, which is all I want on a fishing knife. Six months of salt water and it still looks new.',
				],
			],
			'brass'   => [
				[
					'rating' => 5,
					'body'   => 'Diameter is consistent — went straight into a 3mm hole with no slop and peened without splitting.',
				],
				[
					'rating' => 4,
					'body'   => 'Long rods mean one order covers a lot of knives. Deburr the cut ends and they are perfect.',
				],
			],
		];
	}

	/**
	 * Fictional customer accounts for the demo dataset.
	 *
	 * @return array<int, array{first:string, last:string, email:string, country:string, state:string, city:string, postcode:string, address:string, phone:string, wholesale:bool}>
	 */
	public static function customers(): array {
		return [
			[
				'first'     => 'Marcus',
				'last'      => 'Hillard',
				'email'     => 'marcus.hillard@example.com',
				'country'   => 'US',
				'state'     => 'OR',
				'city'      => 'Portland',
				'postcode'  => '97205',
				'address'   => '418 SW Alder Street, Unit 6',
				'phone'     => '+1 503 555 0142',
				'wholesale' => false,
			],
			[
				'first'     => 'Ellen',
				'last'      => 'Roache',
				'email'     => 'ellen.roache@example.com',
				'country'   => 'GB',
				'state'     => '',
				'city'      => 'Sheffield',
				'postcode'  => 'S3 8EN',
				'address'   => '12 Kelham Island Works',
				'phone'     => '+44 114 496 0118',
				'wholesale' => true,
			],
			[
				'first'     => 'Tomas',
				'last'      => 'Lindqvist',
				'email'     => 'tomas.lindqvist@example.com',
				'country'   => 'SE',
				'state'     => '',
				'city'      => 'Gothenburg',
				'postcode'  => '411 05',
				'address'   => 'Vasagatan 27',
				'phone'     => '+46 31 555 0193',
				'wholesale' => false,
			],
			[
				'first'     => 'Callum',
				'last'      => 'Reid',
				'email'     => 'callum.reid@example.com',
				'country'   => 'AU',
				'state'     => 'VIC',
				'city'      => 'Melbourne',
				'postcode'  => '3000',
				'address'   => '88 Little Bourke Street',
				'phone'     => '+61 3 5550 0177',
				'wholesale' => false,
			],
			[
				'first'     => 'Ana',
				'last'      => 'Molnar',
				'email'     => 'ana.molnar@example.com',
				'country'   => 'DE',
				'state'     => '',
				'city'      => 'Leipzig',
				'postcode'  => '04109',
				'address'   => 'Gottschedstrasse 14',
				'phone'     => '+49 341 5550 214',
				'wholesale' => true,
			],
			[
				'first'     => 'Priya',
				'last'      => 'Raghavan',
				'email'     => 'priya.raghavan@example.com',
				'country'   => 'IN',
				'state'     => 'KA',
				'city'      => 'Bengaluru',
				'postcode'  => '560001',
				'address'   => '22 Cunningham Road',
				'phone'     => '+91 80 5550 3311',
				'wholesale' => false,
			],
			[
				'first'     => 'Jesse',
				'last'      => 'Vandermeer',
				'email'     => 'jesse.vandermeer@example.com',
				'country'   => 'CA',
				'state'     => 'BC',
				'city'      => 'Victoria',
				'postcode'  => 'V8W 1P6',
				'address'   => '740 Johnson Street',
				'phone'     => '+1 250 555 0166',
				'wholesale' => false,
			],
			[
				'first'     => 'Sophie',
				'last'      => 'Bertrand',
				'email'     => 'sophie.bertrand@example.com',
				'country'   => 'FR',
				'state'     => '',
				'city'      => 'Lyon',
				'postcode'  => '69002',
				'address'   => '9 Rue des Marronniers',
				'phone'     => '+33 4 5550 8821',
				'wholesale' => false,
			],
		];
	}

	/**
	 * Packing note text used on demo orders.
	 *
	 * @return string[]
	 */
	public static function packing_notes(): array {
		return [
			'Pair matched for grain before packing. Wrapped separately, boxed with corner padding.',
			'Customer asked for the closest colour match across both pairs — lots noted on the packing slip.',
			'Export documents attached to the outside of the carton. Declared value matches the invoice.',
			'Horn wrapped in tissue to avoid rub marks in transit. Do not vacuum pack.',
			'Trade order — no retail packaging, materials boxed by lot with reference labels.',
		];
	}
}
