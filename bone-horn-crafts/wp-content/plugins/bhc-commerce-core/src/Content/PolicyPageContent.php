<?php
/**
 * Copy for the store's policy pages.
 *
 * @package BoneHornCrafts\Core
 */

declare( strict_types = 1 );

namespace BoneHornCrafts\Core\Content;

defined( 'ABSPATH' ) || exit;

use BoneHornCrafts\Core\Store\BusinessDetails;

/**
 * Builds the body copy for the legal and contact pages.
 *
 * Separated from the installer that publishes them so the copy can be read,
 * reviewed and changed without touching page-creation logic, and so the
 * installer has nothing to test but publishing.
 *
 * On the disclaimers: every page here states plainly that it should be reviewed
 * before a business relies on it. That is not boilerplate hedging — this copy
 * describes how the store is built and operated, which is genuinely useful and
 * genuinely accurate, but whether it satisfies the consumer, data-protection
 * and export rules of any particular jurisdiction is a question for someone
 * qualified to answer it.
 */
final class PolicyPageContent {

	/**
	 * Constructor.
	 *
	 * @param BusinessDetails $business Business details.
	 */
	public function __construct( private BusinessDetails $business ) {}

	/**
	 * All pages, keyed by slug.
	 *
	 * @return array<string, array{title:string, excerpt:string, content:string}>
	 */
	public function all(): array {
		return [
			'contact'           => $this->contact(),
			'privacy-policy'    => $this->privacy(),
			'terms-conditions'  => $this->terms(),
			'shipping-delivery' => $this->shipping(),
			'returns-refunds'   => $this->returns(),
		];
	}

	/**
	 * A single page by slug.
	 *
	 * @param string $slug Page slug.
	 *
	 * @return array{title:string, excerpt:string, content:string}|null
	 */
	public function get( string $slug ): ?array {
		return $this->all()[ $slug ] ?? null;
	}

	/**
	 * The address block, reused across several pages.
	 */
	private function address_block(): string {
		$lines = array_map(
			static fn ( string $line ): string => esc_html( $line ),
			$this->business->address_lines()
		);

		return '<address>'
			. '<strong>' . esc_html( $this->business->name() ) . '</strong><br />'
			. implode( '<br />', $lines )
			. '<br /><br />'
			. 'Phone: <a href="tel:' . esc_attr( $this->business->phone_href() ) . '">' . esc_html( $this->business->phone() ) . '</a><br />'
			. 'Email: <a href="mailto:' . esc_attr( $this->business->email() ) . '">' . esc_html( $this->business->email() ) . '</a>'
			. '</address>';
	}

	/**
	 * A note asking for the page to be reviewed before it is relied on.
	 *
	 * @param string $subject What needs reviewing.
	 */
	private function review_note( string $subject ): string {
		return '<p class="bhc-policy-note"><em>'
			. esc_html(
				sprintf(
					/* translators: %s: what should be reviewed, e.g. "these terms". */
					__( 'Please have %s reviewed against the consumer, tax and data-protection rules that apply where you sell before relying on them. They describe how this store actually operates; they are not legal advice.', 'bhc-commerce-core' ),
					$subject
				)
			)
			. '</em></p>';
	}

	/**
	 * Contact page.
	 *
	 * @return array{title:string, excerpt:string, content:string}
	 */
	private function contact(): array {
		return [
			'title'   => __( 'Contact Us', 'bhc-commerce-core' ),
			'excerpt' => __( 'Where the workshop is, how to reach us, and what to include so we can answer in one reply.', 'bhc-commerce-core' ),
			'content' => '<p>' . esc_html__( 'Questions about material, sizing, a live order or a wholesale quantity all come to the same inbox and are answered by the people who cut the stock.', 'bhc-commerce-core' ) . '</p>'
				. '<h2>' . esc_html__( 'Workshop and registered address', 'bhc-commerce-core' ) . '</h2>'
				. $this->address_block()
				. '<h2>' . esc_html__( 'When we reply', 'bhc-commerce-core' ) . '</h2>'
				. '<p>' . esc_html__( 'Monday to Saturday, within one working day. The workshop runs on India Standard Time (UTC+5:30), so a message sent from Europe or the Americas in the afternoon is usually answered overnight.', 'bhc-commerce-core' ) . '</p>'
				. '<h2>' . esc_html__( 'What to include', 'bhc-commerce-core' ) . '</h2>'
				. '<ul>'
				. '<li>' . esc_html__( 'For an existing order: the order number from your confirmation email.', 'bhc-commerce-core' ) . '</li>'
				. '<li>' . esc_html__( 'For material questions: what you are building and the finished dimensions you need.', 'bhc-commerce-core' ) . '</li>'
				. '<li>' . esc_html__( 'For wholesale: quantity, destination country and how often you expect to reorder.', 'bhc-commerce-core' ) . '</li>'
				. '</ul>'
				. '<h2>' . esc_html__( 'Wholesale and export enquiries', 'bhc-commerce-core' ) . '</h2>'
				. '<p>' . esc_html__( 'We supply makers, distributors and manufacturers worldwide. Tell us the quantity and destination and you will get a quote including freight and the documentation your customs authority will expect.', 'bhc-commerce-core' ) . '</p>',
		];
	}

	/**
	 * Privacy policy.
	 *
	 * @return array{title:string, excerpt:string, content:string}
	 */
	private function privacy(): array {
		return [
			'title'   => __( 'Privacy Policy', 'bhc-commerce-core' ),
			'excerpt' => __( 'What we collect when you order, why we need it, how long we keep it and how to have it removed.', 'bhc-commerce-core' ),
			'content' => $this->review_note( __( 'this policy', 'bhc-commerce-core' ) )
				. '<h2>' . esc_html__( 'Who is responsible for your data', 'bhc-commerce-core' ) . '</h2>'
				. $this->address_block()
				. '<h2>' . esc_html__( 'What is collected', 'bhc-commerce-core' ) . '</h2>'
				. '<p>' . esc_html__( 'When you place an order: your name, delivery and billing address, email address and phone number. The phone number is not optional for export parcels — couriers require a reachable number at the destination and will hold a shipment without one.', 'bhc-commerce-core' ) . '</p>'
				. '<p>' . esc_html__( 'When you create an account: the same details, kept so you do not retype them, plus your order history.', 'bhc-commerce-core' ) . '</p>'
				. '<p>' . esc_html__( 'When you subscribe to the mailing list: your email address, the date you confirmed and where you subscribed from. Nothing else.', 'bhc-commerce-core' ) . '</p>'
				. '<p>' . esc_html__( 'When you browse: a session cookie for the cart, and — only if you use those features — a cookie holding the product IDs on your wishlist or recently viewed list. Those cookies contain product IDs and nothing else: no name, no email, no advertising identifier.', 'bhc-commerce-core' ) . '</p>'
				. '<h2>' . esc_html__( 'Payment details', 'bhc-commerce-core' ) . '</h2>'
				. '<p>' . esc_html__( 'Card and PayPal details are entered with the payment provider and never reach this site. We receive confirmation that a payment succeeded, the amount, and the billing name and address — never a full card number.', 'bhc-commerce-core' ) . '</p>'
				. '<h2>' . esc_html__( 'What is not collected', 'bhc-commerce-core' ) . '</h2>'
				. '<p>' . esc_html__( 'No advertising trackers, no cross-site profiling, and no sale or sharing of personal data with brokers.', 'bhc-commerce-core' ) . '</p>'
				. '<h2>' . esc_html__( 'Who it is shared with', 'bhc-commerce-core' ) . '</h2>'
				. '<p>' . esc_html__( 'Only the parties needed to complete your order: the payment provider that takes the payment, the courier that carries the parcel, and the customs authorities of the destination country, which receive the declaration on the commercial invoice.', 'bhc-commerce-core' ) . '</p>'
				. '<h2>' . esc_html__( 'How long it is kept', 'bhc-commerce-core' ) . '</h2>'
				. '<p>' . esc_html__( 'Order and invoice records are kept for as long as tax and customs rules require us to be able to produce them. Account details are kept until you ask for them to be deleted. Mailing-list entries are kept until you unsubscribe, and every email carries a one-click unsubscribe link.', 'bhc-commerce-core' ) . '</p>'
				. '<h2>' . esc_html__( 'Your choices', 'bhc-commerce-core' ) . '</h2>'
				. '<p>' . esc_html__( 'You can ask for a copy of the data held about you, ask for it to be corrected, or ask for your account and its data to be erased — except for records we are required to retain for tax purposes. Email the address above and you will get a reply within one working day.', 'bhc-commerce-core' ) . '</p>',
		];
	}

	/**
	 * Terms and conditions.
	 *
	 * @return array{title:string, excerpt:string, content:string}
	 */
	private function terms(): array {
		return [
			'title'   => __( 'Terms &amp; Conditions', 'bhc-commerce-core' ),
			'excerpt' => __( 'The terms we sell under, and what to expect from an order.', 'bhc-commerce-core' ),
			'content' => $this->review_note( __( 'these terms', 'bhc-commerce-core' ) )
				. '<h2>' . esc_html__( 'Who you are buying from', 'bhc-commerce-core' ) . '</h2>'
				. $this->address_block()
				. '<h2>' . esc_html__( 'Orders', 'bhc-commerce-core' ) . '</h2>'
				. '<p>' . esc_html__( 'An order is an offer to buy. It is accepted when the goods are dispatched. If an item turns out to be unavailable after you order — which happens with one-off pieces — you are told and refunded rather than sent a substitute without being asked.', 'bhc-commerce-core' ) . '</p>'
				. '<h2>' . esc_html__( 'Pricing', 'bhc-commerce-core' ) . '</h2>'
				. '<p>' . esc_html__( 'Prices exclude import duties and taxes charged on arrival in the destination country. Quantity pricing is applied automatically in the cart once the threshold is reached; you do not need a code.', 'bhc-commerce-core' ) . '</p>'
				. '<h2>' . esc_html__( 'Description of goods', 'bhc-commerce-core' ) . '</h2>'
				. '<p>' . esc_html__( 'Photographs show representative material. Dimensions are nominal with a tolerance of one millimetre on thickness. Natural variation in colour, figure and small inclusions is expected in bone, horn and wood, and the grading band for each listing is stated on the product page.', 'bhc-commerce-core' ) . '</p>'
				. '<h2>' . esc_html__( 'Export, permits and restricted material', 'bhc-commerce-core' ) . '</h2>'
				. '<p>' . esc_html__( 'We supply domesticated-animal by-products — cattle and camel bone, water buffalo and rams horn — and cultivated timber. We do not deal in ivory, tortoiseshell, or any CITES-restricted species. Import rules for animal-origin material still vary by country, and checking what your own country permits before ordering is your responsibility.', 'bhc-commerce-core' ) . '</p>'
				. '<h2>' . esc_html__( 'Liability', 'bhc-commerce-core' ) . '</h2>'
				. '<p>' . esc_html__( 'Material is sold as raw stock for skilled work. Cutting, grinding and finishing carry real risk; extraction, eye protection and respiratory protection are the responsibility of the person doing the work.', 'bhc-commerce-core' ) . '</p>'
				. '<h2>' . esc_html__( 'Statutory rights', 'bhc-commerce-core' ) . '</h2>'
				. '<p>' . esc_html__( 'Nothing in these terms limits any statutory right that applies where you live.', 'bhc-commerce-core' ) . '</p>',
		];
	}

	/**
	 * Shipping and delivery.
	 *
	 * @return array{title:string, excerpt:string, content:string}
	 */
	private function shipping(): array {
		return [
			'title'   => __( 'Shipping &amp; Delivery', 'bhc-commerce-core' ),
			'excerpt' => __( 'Dispatch times, transit windows and how export parcels are documented.', 'bhc-commerce-core' ),
			'content' => '<p>' . esc_html__( 'Orders are picked and packed at the workshop. Items showing a lead time need cutting or finishing first; that time is added before dispatch and is shown on the product page rather than discovered afterwards.', 'bhc-commerce-core' ) . '</p>'
				. '<h2>' . esc_html__( 'Dispatch', 'bhc-commerce-core' ) . '</h2>'
				. '<p>' . esc_html__( 'In-stock items leave within one working day. Items with a stated lead time leave within that window. If a batch runs late you are emailed before your dispatch date, not after it.', 'bhc-commerce-core' ) . '</p>'
				. '<h2>' . esc_html__( 'Transit windows', 'bhc-commerce-core' ) . '</h2>'
				. '<table><thead><tr><th>' . esc_html__( 'Destination', 'bhc-commerce-core' ) . '</th><th>' . esc_html__( 'Typical transit', 'bhc-commerce-core' ) . '</th></tr></thead><tbody>'
				. '<tr><td>' . esc_html__( 'India (domestic)', 'bhc-commerce-core' ) . '</td><td>' . esc_html__( '2-5 working days', 'bhc-commerce-core' ) . '</td></tr>'
				. '<tr><td>' . esc_html__( 'United Kingdom', 'bhc-commerce-core' ) . '</td><td>' . esc_html__( '5-8 working days', 'bhc-commerce-core' ) . '</td></tr>'
				. '<tr><td>' . esc_html__( 'Europe', 'bhc-commerce-core' ) . '</td><td>' . esc_html__( '6-10 working days', 'bhc-commerce-core' ) . '</td></tr>'
				. '<tr><td>' . esc_html__( 'United States &amp; Canada', 'bhc-commerce-core' ) . '</td><td>' . esc_html__( '6-11 working days', 'bhc-commerce-core' ) . '</td></tr>'
				. '<tr><td>' . esc_html__( 'Australia &amp; New Zealand', 'bhc-commerce-core' ) . '</td><td>' . esc_html__( '7-13 working days', 'bhc-commerce-core' ) . '</td></tr>'
				. '</tbody></table>'
				. '<p>' . esc_html__( 'These are transit times after dispatch, not from the moment you order, and they exclude customs holds.', 'bhc-commerce-core' ) . '</p>'
				. '<h2>' . esc_html__( 'Customs and documentation', 'bhc-commerce-core' ) . '</h2>'
				. '<p>' . esc_html__( 'Every export parcel ships with an accurate commercial invoice listing the material description, HS code, quantity and declared value. We do not mark parcels as gifts or under-declare their value; doing so puts your parcel at risk, not ours.', 'bhc-commerce-core' ) . '</p>'
				. '<h2>' . esc_html__( 'Duties and taxes', 'bhc-commerce-core' ) . '</h2>'
				. '<p>' . esc_html__( 'Import duty, VAT, GST or sales tax charged on arrival is payable by the recipient. Rates depend on your country and the value of the shipment. If a parcel is refused at customs and returned to us, the return freight is deducted from any refund.', 'bhc-commerce-core' ) . '</p>'
				. '<h2>' . esc_html__( 'Questions about a shipment', 'bhc-commerce-core' ) . '</h2>'
				. $this->address_block(),
		];
	}

	/**
	 * Returns and refunds.
	 *
	 * @return array{title:string, excerpt:string, content:string}
	 */
	private function returns(): array {
		return [
			'title'   => __( 'Returns &amp; Refunds', 'bhc-commerce-core' ),
			'excerpt' => __( 'Thirty days to change your mind on unworked material.', 'bhc-commerce-core' ),
			'content' => '<p>' . esc_html__( 'Material that is not right for your build can come back within thirty days of delivery, provided it has not been cut, drilled, sanded or dyed.', 'bhc-commerce-core' ) . '</p>'
				. '<h2>' . esc_html__( 'What can be returned', 'bhc-commerce-core' ) . '</h2>'
				. '<ul>'
				. '<li>' . esc_html__( 'Unworked material in its original condition', 'bhc-commerce-core' ) . '</li>'
				. '<li>' . esc_html__( 'Sealed sets and packs that have not been opened', 'bhc-commerce-core' ) . '</li>'
				. '<li>' . esc_html__( 'Anything that arrived damaged or is not what you ordered', 'bhc-commerce-core' ) . '</li>'
				. '</ul>'
				. '<h2>' . esc_html__( 'What cannot', 'bhc-commerce-core' ) . '</h2>'
				. '<ul>'
				. '<li>' . esc_html__( 'Material that has been worked in any way — once it is cut it is yours', 'bhc-commerce-core' ) . '</li>'
				. '<li>' . esc_html__( 'Custom-cut sizes made to your dimensions', 'bhc-commerce-core' ) . '</li>'
				. '<li>' . esc_html__( 'Workshop-grade seconds, which are sold as flawed material', 'bhc-commerce-core' ) . '</li>'
				. '</ul>'
				. '<h2>' . esc_html__( 'How it works', 'bhc-commerce-core' ) . '</h2>'
				. '<p>' . esc_html__( 'Email the order number and what you would like to return. You will get a returns reference; write it on the parcel. Return freight is at your cost unless the item was damaged, faulty or incorrectly supplied, in which case we cover it.', 'bhc-commerce-core' ) . '</p>'
				. $this->address_block()
				. '<h2>' . esc_html__( 'Refunds', 'bhc-commerce-core' ) . '</h2>'
				. '<p>' . esc_html__( 'Refunds are issued to the original payment method within five working days of the parcel arriving and being checked. Original outbound shipping is refunded when the fault was ours.', 'bhc-commerce-core' ) . '</p>'
				. '<h2>' . esc_html__( 'Natural variation is not a fault', 'bhc-commerce-core' ) . '</h2>'
				. '<p>' . esc_html__( 'Colour, figure and small inclusions vary between pieces of the same listing. Our photographs show representative material and every listing states its grading band. Variation within that band is not grounds for a fault return — though it is always grounds for an email if you think we have misjudged it.', 'bhc-commerce-core' ) . '</p>',
		];
	}
}
