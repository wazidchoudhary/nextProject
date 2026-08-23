<?php
/**
 * Editorial content for the demo store.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Demo;

defined( 'ABSPATH' ) || exit;

/**
 * Page and article copy for the demo build.
 *
 * All copy is original and written for this reference store. Policies describe
 * how a workshop of this kind typically operates and are illustrative demo
 * content, not legal advice or a binding commitment.
 */
final class ContentLibrary {

	/**
	 * Static pages: slug => definition.
	 *
	 * @return array<string, array{title:string, excerpt:string, content:string, template:string, menu:string}>
	 */
	public static function pages(): array {
		return [
			'home'                 => [
				'title'    => 'Home',
				'excerpt'  => 'Hand-selected bone, horn and wood craft materials, finished for makers who care about detail.',
				'template' => 'front-page',
				'menu'     => 'primary',
				'content'  => '<p>Bone Horn Crafts supplies knife makers, luthiers, pen turners and leather workers with natural handle and blank material, cut and finished in our own workshop and shipped worldwide.</p>',
			],
			'new-arrivals'         => [
				'title'    => 'New Arrivals',
				'excerpt'  => 'The most recent material to come off the bench, listed as it is cut.',
				'template' => 'page-products',
				'menu'     => 'primary',
				'content'  => '<p>Material is listed here the week it is cut. Bark-edge horn and rams horn move fastest — those pairs are one-offs, so when a listing goes out of stock it is genuinely gone until the next lot comes through.</p>'
					. '[bhc_product_grid source="new" limit="12" columns="4"]'
					. '<h2>What lands here first</h2>'
					. '<p>Every batch is cut, flattened, graded and photographed before it is listed. Pairs that miss our first grade for cosmetic reasons are listed separately as workshop grade rather than quietly mixed into the main listings.</p>',
			],
			'bestsellers'          => [
				'title'    => 'Bestsellers',
				'excerpt'  => 'The material makers come back for, ranked by what actually ships.',
				'template' => 'page-products',
				'menu'     => 'primary',
				'content'  => '<p>This ranking is generated from real order volume over the last thirty days by the merchandising index, not curated by hand. If a listing drops off, it is because something else sold more.</p>'
					. '[bhc_product_grid source="bestsellers" limit="12" columns="4"]'
					. '<h2>Why these ones</h2>'
					. '<p>The pattern repeats every year: plain white bone and black buffalo horn in the standard 5 x 1.5 inch scale size outsell everything decorative, because they are what a maker reaches for when a commission has to look right rather than look unusual.</p>',
			],
			'wishlist'             => [
				'title'    => 'Wishlist',
				'excerpt'  => 'Material you have saved for a build you have not started yet.',
				'template' => 'page-wide',
				'menu'     => 'utility',
				'content'  => '<p>Saved material stays on this list while you plan. Signed-in customers keep their list across devices; if you are browsing as a guest it is stored in your browser until you create an account.</p>[bhc_wishlist]',
			],
			'about-us'             => [
				'title'    => 'About Us',
				'excerpt'  => 'A materials workshop that cuts, grades and finishes everything it sells.',
				'template' => 'page-narrow',
				'menu'     => 'primary',
				'content'  => '<p class="lead">We are a materials workshop, not a warehouse. Every scale, blank and comb we list is cut, flattened, graded and finished on our own benches before it is photographed.</p>'
					. '<h2>How the work happens</h2>'
					. '<p>Raw bone and horn arrive as by-product material from the food industry. It is cleaned, boiled, degreased and dried for six to eight weeks before anything is cut from it. Rushing that stage is the single most common reason a bone handle smells or a horn scale warps six months after a knife is finished, so we do not rush it.</p>'
					. '<p>Cutting is done on a bandsaw with the operator matching pieces by eye as they come off the block. Pairs are marked in sequence, flattened together and kept together through grading and packing, which is why a matched pair from us has grain that continues across the handle rather than two pieces that merely look similar.</p>'
					. '<h2>Grading, honestly</h2>'
					. '<p>Natural material is not uniform, and pretending otherwise ends in disappointed customers. We grade in three bands: first grade for pairs with even colour and no visible pitting, second for sound material with cosmetic marks, and workshop grade for pieces that are structurally fine but obviously flawed. Workshop grade is listed as workshop grade at workshop-grade prices.</p>'
					. '<h2>Who buys from us</h2>'
					. '<p>Roughly two thirds of what leaves the bench goes to individual makers in the United States, the United Kingdom, Germany and Australia. The rest goes to teaching workshops, luthier benches and small production shops buying by the box. Both matter: the classes tell us what beginners struggle with, the production shops tell us where our tolerances slip.</p>'
					. '<h2>Materials we will not sell</h2>'
					. '<p>No ivory, no protected species, no material we cannot document from source. There are legal substitutes for every one of them and we stock those instead.</p>',
			],
			'contact'              => [
				'title'    => 'Contact',
				'excerpt'  => 'Reach the workshop about an order, a bulk enquiry or a material question.',
				'template' => 'page-contact',
				'menu'     => 'primary',
				'content'  => '<p>The bench answers email between 09:00 and 18:00 IST, Monday to Saturday. Order questions are usually answered the same working day; material and bulk enquiries can take two, because someone has to go and look at what is actually on the rack.</p>'
					. '<h2>Before you write</h2>'
					. '<ul><li><strong>Order status:</strong> tracking is emailed when the parcel is scanned. If the email has not arrived, check the order in your account first.</li>'
					. '<li><strong>Bulk and wholesale:</strong> tell us the item, the annual quantity and the destination country, and you will get a landed price rather than a list price.</li>'
					. '<li><strong>Material questions:</strong> send the dimensions you need. "Will this work for a chef knife" is easier to answer with a length and a thickness.</li></ul>',
			],
			'faq'                  => [
				'title'    => 'FAQ',
				'excerpt'  => 'Straight answers on material, sizing, shipping and returns.',
				'template' => 'page-faq',
				'menu'     => 'footer',
				'content'  => '<h2>Material</h2>'
					. '<h3>Will bone or horn smell when I work it?</h3>'
					. '<p>Properly degreased material does not smell in use. Grinding any organic material produces dust with an odour while you are cutting — run extraction and a mask, as you would with exotic timber.</p>'
					. '<h3>Is horn stable enough for a kitchen knife?</h3>'
					. '<p>Yes, provided the handle is sealed at the pins and the knife is not left in water. Horn moves with prolonged soaking; so does wood.</p>'
					. '<h3>Do matched pairs really match?</h3>'
					. '<p>They are cut in sequence from one block and stay together through grading and packing. Colour and figure continue across the pair. They are not identical, because natural material is not.</p>'
					. '<h2>Sizing</h2>'
					. '<h3>What size scales do I need?</h3>'
					. '<p>Our standard scale is 5 x 1.5 inch, which covers most hunters, folders and small kitchen knives. Chef knives and bowies need the 6 x 2 inch slabs. The size guide has a full table.</p>'
					. '<h3>Can you cut a custom size?</h3>'
					. '<p>For orders of ten pairs or more, yes. Email the dimensions and tolerance you need.</p>'
					. '<h2>Orders and shipping</h2>'
					. '<h3>How long does delivery take?</h3>'
					. '<p>Most destinations are six to twelve working days after dispatch. The estimator on each product page gives a window for your country.</p>'
					. '<h3>Do I pay import duty?</h3>'
					. '<p>Duties and taxes on arrival are the buyer\'s responsibility and vary by country. We declare every parcel accurately.</p>'
					. '<h3>Can I change an order after placing it?</h3>'
					. '<p>Until it is packed, yes. Reply to your order confirmation and we will catch it if the parcel has not gone.</p>',
			],
			'material-size-guide'  => [
				'title'    => 'Material &amp; Size Guide',
				'excerpt'  => 'Choosing between bone, horn, wood and acrylic, and what size to order.',
				'template' => 'page-narrow',
				'menu'     => 'footer',
				'content'  => '<p>Two questions decide most orders: which material suits the build, and how much of it you need. This page answers both.</p>'
					. '<h2>Material at a glance</h2>'
					. '<table><thead><tr><th>Material</th><th>Density</th><th>Works like</th><th>Best for</th></tr></thead><tbody>'
					. '<tr><td>Camel bone</td><td>High</td><td>Dense hardwood</td><td>First bone handle, jigged work, dyeing</td></tr>'
					. '<tr><td>Cattle bone</td><td>Medium-high</td><td>Dense hardwood</td><td>Slip joints, spacers, folders</td></tr>'
					. '<tr><td>Water buffalo horn</td><td>Medium</td><td>Dense plastic</td><td>Black handles, bolsters, pins, tableware</td></tr>'
					. '<tr><td>Rams horn</td><td>Medium-low</td><td>Layered horn</td><td>Traditional hunters, display pieces</td></tr>'
					. '<tr><td>Stabilized burl</td><td>Medium</td><td>Hard resin-filled wood</td><td>Colour and figure without movement</td></tr>'
					. '<tr><td>Acrylic</td><td>Medium</td><td>Cast plastic</td><td>Wet-use knives, learning, bright colour</td></tr>'
					. '</tbody></table>'
					. '<h2>Scale sizing</h2>'
					. '<table><thead><tr><th>Knife type</th><th>Scale size</th><th>Thickness</th></tr></thead><tbody>'
					. '<tr><td>Folder / slip joint</td><td>4.5 x 1.25 in</td><td>0.25 in</td></tr>'
					. '<tr><td>Hunter / EDC fixed blade</td><td>5 x 1.5 in</td><td>0.30-0.375 in</td></tr>'
					. '<tr><td>Chef knife</td><td>6 x 2 in</td><td>0.375 in</td></tr>'
					. '<tr><td>Bowie / carved handle</td><td>6 x 2 in slab</td><td>0.47-0.59 in</td></tr>'
					. '</tbody></table>'
					. '<h2>Guitar blanks</h2>'
					. '<p>Nut blanks are supplied at 45 x 6 x 9mm and saddle blanks at 80 x 3 x 10mm — oversize on every dimension so they can be fitted to an existing slot rather than forcing the slot to fit the blank.</p>'
					. '<h2>Pen blanks</h2>'
					. '<p>Standard blanks are 5 x 0.75 x 0.75 in, which covers the common two-barrel kits with material to spare for a centre band.</p>',
			],
			'care-finishing-guide' => [
				'title'    => 'Care &amp; Finishing Guide',
				'excerpt'  => 'How to sand, polish and look after bone, horn, wood and acrylic.',
				'template' => 'page-narrow',
				'menu'     => 'footer',
				'content'  => '<p>Natural material rewards patience and punishes heat. These are the finishing routines we use on the bench.</p>'
					. '<h2>Bone</h2>'
					. '<p>Work dry with extraction running. Rough shape at 120 grit, then step through 220, 400, 800 and 1500, wet from 400 upward. Buff on a clean cotton wheel with a light compound. A wipe of mineral oil every few months keeps the surface from drying and chalking.</p>'
					. '<h2>Horn</h2>'
					. '<p>Heat is the enemy: horn delaminates if you cook it. Keep grinder passes short, dunk in water between passes, and never clamp a warm piece. Finish through to 1500 grit and buff lightly. Keep finished pieces out of direct sun and away from dishwashers.</p>'
					. '<h2>Stabilized wood</h2>'
					. '<p>No sealing needed. Sharp tools, light passes, sand to 800 grit and buff. If you are finishing untreated hardwood instead, oil after shaping and again when the handle is assembled.</p>'
					. '<h2>Acrylic</h2>'
					. '<p>Cut slowly; acrylic smears before it burns. Wet sand from 400 to 2000 grit and polish with plastic compound on a loose wheel. Avoid solvent cleaners on the finished handle.</p>'
					. '<h2>Looking after finished pieces</h2>'
					. '<p>Hand wash horn and bone tableware in warm water and dry immediately. Do not soak, do not put them in a dishwasher, and do not leave them on a windowsill. Treated that way, a horn mug outlives the person who bought it.</p>',
			],
			'order-tracking'       => [
				'title'    => 'Order Tracking',
				'excerpt'  => 'Look up an order with its number and billing email.',
				'template' => 'page-narrow',
				'menu'     => 'utility',
				'content'  => '<p>Enter the order number from your confirmation email together with the billing email address to see the current status. Tracking numbers appear here as soon as the parcel is scanned by the courier.</p>'
					. '[woocommerce_order_tracking]'
					. '<h2>What the statuses mean</h2>'
					. '<ul><li><strong>Processing</strong> — payment received, the order is in the queue to be picked or cut.</li>'
					. '<li><strong>On hold</strong> — waiting on stock or on a reply from you.</li>'
					. '<li><strong>Completed</strong> — dispatched. The tracking email goes out at the same time.</li></ul>',
			],
			'blog'                 => [
				'title'    => 'Workshop Journal',
				'excerpt'  => 'Notes from the bench on material, technique and what we are cutting.',
				'template' => 'page-blog',
				'menu'     => 'primary',
				'content'  => '<p>Notes from the bench: how the material behaves, what we have learned cutting it, and the occasional correction when we get something wrong.</p>',
			],
		];
	}

	/**
	 * Journal articles.
	 *
	 * @return array<int, array{slug:string, title:string, excerpt:string, content:string, material:string, shape:string}>
	 */
	public static function articles(): array {
		return [
			[
				'slug'     => 'choosing-between-bone-and-horn',
				'title'    => 'Choosing between bone and horn for a first handle',
				'excerpt'  => 'They look like alternatives on a listing page. On the bench they behave nothing alike.',
				'material' => 'camel-bone',
				'shape'    => 'scale',
				'content'  => '<p>The question we get most often from makers building their first natural-material handle is whether to start with bone or horn. The honest answer is bone, and the reason has nothing to do with which one looks better.</p>'
					. '<h2>Bone forgives, horn remembers</h2>'
					. '<p>Bone is dense and inert. It cuts at whatever speed you run the belt, it does not care much about heat, and if you sand through a high spot you can carry on. Horn is a keratin laminate. Get it hot and the layers separate; clamp it warm and it takes a set you will not get back out.</p>'
					. '<h2>What that means in practice</h2>'
					. '<p>On a first handle you will grind too long in one place, because everyone does. On bone that leaves a flat spot. On horn it leaves a delamination you find three weeks later when the handle has been finished and pinned.</p>'
					. '<h2>When horn is the right answer</h2>'
					. '<p>Once you are controlling heat — short passes, water between them, a fan on the piece — horn gives you something bone cannot: depth. A polished black buffalo horn scale has a wet look that reads as expensive from across a table. Nothing in acrylic imitates it convincingly.</p>'
					. '<h2>A practical starting order</h2>'
					. '<p>Two pairs of plain white bone, one pair of black buffalo horn. Build the two bone handles first, then the horn one. You will spend about forty dollars finding out which material you actually enjoy working, which is cheaper than finding out on a commission.</p>',
			],
			[
				'slug'     => 'why-we-degrease-for-eight-weeks',
				'title'    => 'Why we degrease bone for eight weeks',
				'excerpt'  => 'The single stage that decides whether a bone handle smells in year two.',
				'material' => 'cattle-bone',
				'shape'    => 'scale',
				'content'  => '<p>Bone that has been cleaned quickly looks identical to bone that has been cleaned properly. The difference shows up eighteen months later, in someone else\'s workshop, on a knife with our material on it.</p>'
					. '<h2>What is actually in there</h2>'
					. '<p>Fresh bone carries marrow fat through its structure, not just on the surface. Boil it once and you remove what is on the outside. What is left inside migrates slowly to the surface over the following year and oxidises, which is what people mean when they say a bone handle "went yellow and started to smell".</p>'
					. '<h2>The long version</h2>'
					. '<p>Our stock is boiled, scraped, boiled again in a mild alkaline bath, then racked in open air for six to eight weeks with periodic turning. It is a dull process that occupies floor space and produces nothing to sell, which is exactly why it is the first stage to get cut when a supplier is competing on price.</p>'
					. '<h2>How to check material you already have</h2>'
					. '<p>Sand a small area to 400 grit and leave it under a lamp for an hour. Properly degreased bone stays matte and odourless. Under-processed bone develops a faint sheen as fat comes to the surface — and that is what will happen to a finished handle in a warm car.</p>',
			],
			[
				'slug'     => 'setting-up-a-nut-and-saddle-in-bone',
				'title'    => 'Fitting a bone nut and saddle without a milling machine',
				'excerpt'  => 'Hand tools, a straight edge and an afternoon are enough.',
				'material' => 'cattle-bone',
				'shape'    => 'blank',
				'content'  => '<p>Swapping the plastic nut and saddle on an entry-level acoustic for bone is the cheapest real improvement available to a player, and it does not need a workshop.</p>'
					. '<h2>Why bone and not the moulded part</h2>'
					. '<p>Moulded nuts are soft and slightly porous. String energy that should travel into the neck is absorbed in the part instead. Bone is dense and returns it, which is heard as more sustain on open strings and a firmer attack.</p>'
					. '<h2>The order of work</h2>'
					. '<p>Remove the old parts intact and keep them: they are your reference for height and radius. Sand the blank to thickness against a sheet of abrasive taped to glass, checking the fit in the slot every few strokes. Get the fit right before you touch the height.</p>'
					. '<h2>Where people go wrong</h2>'
					. '<p>Cutting the string slots too deep, every time. Slot depth sets action at the first fret, and there is no way back up. Cut to half a string diameter, restring, measure, and only then go deeper.</p>'
					. '<h2>Why unbleached</h2>'
					. '<p>Bleached bone is whiter and more porous. Unbleached is a shade creamier, denser, and it is what we supply for exactly that reason.</p>',
			],
			[
				'slug'     => 'reading-a-horn-before-you-cut',
				'title'    => 'Reading a horn before you cut it',
				'excerpt'  => 'Where the pairs are, where the waste is, and what the tip is good for.',
				'material' => 'water-buffalo-horn',
				'shape'    => 'horn',
				'content'  => '<p>A buffalo horn is not uniform stock. Knowing which section you are cutting decides whether you get two good pairs or a box of offcuts.</p>'
					. '<h2>The base</h2>'
					. '<p>Thickest, densest, blackest. This is where knife scales and bolsters come from, and it is the only part with enough wall thickness for a 15mm slab.</p>'
					. '<h2>The middle</h2>'
					. '<p>Where the banding lives. Thinner walls, more grey, and the section that produces marbled scales. Cut it flat and the bands run the length of the piece; cut it at an angle and they swirl.</p>'
					. '<h2>The tip</h2>'
					. '<p>Solid rather than hollow, small, and translucent when sanded thin. Too small for scales, perfect for beads, buttons, shot cups and pen blanks. Any supplier throwing tips away is losing the highest-margin part of the horn.</p>'
					. '<h2>The hollow</h2>'
					. '<p>The inner cavity runs further up than most people expect. Cut a scale from too close to the base and you find the void halfway through flattening. We mark the hollow depth on every horn before the first cut.</p>',
			],
			[
				'slug'     => 'stabilized-versus-untreated-burl',
				'title'    => 'Stabilized versus untreated burl: when it matters',
				'excerpt'  => 'Not every blank needs resin. The ones that do, need it badly.',
				'material' => 'stabilized-wood',
				'shape'    => 'scale',
				'content'  => '<p>Stabilizing costs money and changes how a blank cuts, so it is worth knowing when it earns its place.</p>'
					. '<h2>What stabilizing does</h2>'
					. '<p>The blank is dried below 8% moisture, put under vacuum, flooded with a thin resin and heat cured. The resin fills the cell structure. The blank stops responding to humidity and the soft areas become as hard as the dense ones.</p>'
					. '<h2>When you need it</h2>'
					. '<p>Burl, spalted timber, anything punky, and any blank going into a handle that will see moisture. Untreated burl on a kitchen knife will move at the pins within a year in most climates.</p>'
					. '<h2>When you do not</h2>'
					. '<p>Dense, oily tropical hardwoods — rosewood, ebony, cocobolo — are stable on their own and the resin struggles to penetrate anyway. Oil them and move on.</p>'
					. '<h2>How to spot a poor job</h2>'
					. '<p>Cut the blank in half. Properly stabilized material is uniform through the section; a poor job shows a treated shell and a dry core. That test destroys a blank, which is why buying from someone who does the process themselves matters.</p>',
			],
			[
				'slug'     => 'shipping-natural-materials-worldwide',
				'title'    => 'What actually happens to your parcel at customs',
				'excerpt'  => 'Declarations, HS codes and why we will not mark a parcel as a gift.',
				'material' => 'rams-horn',
				'shape'    => 'blank',
				'content'  => '<p>Most customers never think about the paperwork attached to a parcel of handle material. It is worth five minutes, because it explains a few things we get asked about.</p>'
					. '<h2>Every parcel is declared accurately</h2>'
					. '<p>The commercial invoice lists the material description, the HS code, the quantity and the value actually paid. Under-declaring is fraud, and the person who carries the risk is the recipient, whose parcel is seized.</p>'
					. '<h2>Why the HS code matters</h2>'
					. '<p>Worked bone and horn articles, wooden blanks and instrument parts each sit under a different code with a different duty rate. Using a lazy catch-all code is how a routine parcel ends up in an inspection queue.</p>'
					. '<h2>Duties are yours, and they vary</h2>'
					. '<p>We cannot pre-pay import duty for most destinations. Rates depend on your country and the shipment value, and they are charged before delivery.</p>'
					. '<h2>What we can do</h2>'
					. '<p>Split a large order across two shipments if that keeps each under a de minimis threshold, and send the paperwork in advance if your broker wants it. Ask before you order rather than after.</p>',
			],
		];
	}

	/**
	 * Homepage editorial blocks used by the theme.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function homepage(): array {
		return [
			'hero'       => [
				'eyebrow' => 'Cut, graded and finished in our own workshop',
				'title'   => 'Materials Made for Makers',
				'body'    => 'Bone, horn and wood handle stock, matched in pairs and finished so your build starts right. Shipped worldwide from the bench that cut it.',
				'cta'     => 'Shop New Arrivals',
				'cta_alt' => 'Browse the full catalogue',
			],
			'why'        => [
				'title' => 'Why Bone Horn Crafts',
				'body'  => 'Four things decide whether material is worth buying twice.',
			],
			'newsletter' => [
				'title' => 'New material, first',
				'body'  => 'One email when a batch is cut — bark-edge horn and rams horn sell out before most people see the listing. No campaigns, no resends.',
			],
			'gallery'    => [
				'title' => 'From the bench',
				'body'  => 'Work in progress, batches drying and finished pieces before they are packed.',
			],
			'collection' => [
				'title' => 'Viking &amp; Medieval Collection',
				'body'  => 'Drinking horns, bark-edge scales and horn beads for reenactment kit and Norse-styled builds.',
			],
			'essentials' => [
				'title' => 'Workshop Essentials',
				'body'  => 'Pin stock, spacer sheet and bolster blocks — the parts that finish a build once the scales are shaped.',
			],
		];
	}

	/**
	 * Value proposition tiles for the homepage.
	 *
	 * @return array<int, array{title:string, body:string}>
	 */
	public static function value_props(): array {
		return [
			[
				'title' => 'Matched in pairs',
				'body'  => 'Scales are cut in sequence from one block and stay together through grading and packing, so grain and colour continue across the handle.',
			],
			[
				'title' => 'Graded honestly',
				'body'  => 'Three grading bands, stated on every listing. Cosmetic seconds are sold as seconds at seconds pricing rather than mixed into first grade.',
			],
			[
				'title' => 'Degreased properly',
				'body'  => 'Six to eight weeks of cleaning and drying before anything is cut. It is the stage that decides whether a handle still smells right in year two.',
			],
			[
				'title' => 'Documented exports',
				'body'  => 'Accurate commercial invoices, correct HS codes and lot references on the packing slip, so a parcel clears customs without a phone call.',
			],
		];
	}
}
