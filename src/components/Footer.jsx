import React from 'react';
import Link from 'next/link';
import Image from 'next/image';
const Footer = () => {
    return (
        <footer className="">
            <div className="section-padding-top section-padding-bottom space-db--50 custom-footer">
                <div className="container">
                    <div className="row justify-content-between">
                        <div className="col-lg-5 col-md-6 col-sm-12">
                            <div className="single-footer pb--50">
                                <div className="brand-footer mb--30">
                                    <Image title='Footer Logo' src="/assets/image/logo2.webp" alt="As International Logo" width={350} height={100} />
                                </div>
                                <p>
                                    Where masterful artistry in Bone, Horn, Acrylic, and Wood blends seamlessly with dependable quality, unmatched customer service, and the allure of unique
                                    masterpieces. Dive into our world of timeless beauty and handcrafted perfection.
                                </p>
                                <h3 className="footer-title mb--25 mt--25">Follow Us</h3>
                                <div className="footer-social rounded-icons text-start">
                                    <Link title='Facebook Link' href="https://www.facebook.com/A.SINTERNATIONAL252?mibextid=ZbWKwL" target="_blank" className="single-icon">
                                        <i className="fab fa-facebook-f" />
                                    </Link>
                                    <Link title='Instagram Link' href="https://instagram.com/a.s_international252?utm_source=qr&igshid=MzNlNGNkZWQ4Mg==" className="single-icon" target="_blank">
                                        <i className="fab fa-instagram" />
                                    </Link>
                                </div>
                            </div>
                        </div>
                        <div className="col-lg-2 col-md-6 col-sm-4">
                            <div className="single-footer pb--50">
                                <h3 className="footer-title  mb--30">Products</h3>
                                <ul className="footer-list">
                                    <li>
                                        <Link title='Featured Product Section' href="/#featuredProducts">Featured Products</Link>
                                    </li>
                                    <li>
                                        <Link title='Knife Handles Footer Link' href="/knifeHandles">Knife Handles</Link>
                                    </li>
                                    <li>
                                        <Link title='Products Footer Link' href="/products">More Products</Link>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div className="col-lg-2 col-md-6 col-sm-4">
                            <div className="single-footer pb--50">
                                <h3 className="footer-title  mb--30">Our Company</h3>
                                <ul className="footer-list">
                                    <li>
                                        <Link title='Delivery' href="https://www.fedex.com/en-us/tracking.html" target="_blank">
                                            Delivery
                                        </Link>
                                    </li>

                                    <li>
                                        <Link title='About Us Footer Link' href="/about">About Us</Link>
                                    </li>
                                    <li>
                                        <Link title='Privacy & Policy' href="/privacyPolicy">Privacy & Policy</Link>
                                    </li>
                                    <li>
                                        <Link title='Terms & Conditions' href="/termsConditions">Refund & Return Policy</Link>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div className="col-lg-2 col-md-6 col-sm-4">
                            <div className="single-footer pb--50">
                                <h3 className="footer-title  mb--30">Your Account</h3>
                                <ul className="footer-list">
                                    <li>
                                        <Link title='Cart Footer Link' href="/cart">Cart</Link>
                                    </li>
                                    <li>
                                        <Link title='Checkout Footer Link' href="/checkout">Checkout</Link>
                                    </li>
                                    <li>
                                        <Link title='Contact' href="/contact">Contact us</Link>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div className="copyright custom-footer">
                <div className="container">
                    <div className="row">
                        <div className="col-md-6 text-center text-md-start">
                            <div className="copyright-text">
                                <p>
                                    © AS International, 2023. Made with
                                    <i className="fa fa-heart text-danger ms-1" /> by{' '}
                                    <Link title='Krapton Inc' href="https://krapton.com" target="_blank" className="author">
                                        Krapton Inc
                                    </Link>
                                    .
                                </p>
                            </div>
                        </div>
                        <div className="col-md-6">
                            <ul className="footer-list--inline">
                                <li>
                                    <Link title='Privacy Policy' href="/privacyPolicy">Privacy & Policy</Link>
                                </li>
                                <li>
                                    <Link title='Terms Conditions' href="/termsConditions">Term &amp; Conditions</Link>
                                </li>
                                {/* <li>
                                    <Link href="/affiliate">Affiliate</Link>
                                </li>
                                <li>
                                    <Link href="/help">Help</Link>
                                </li> */}
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    );
};
export default Footer;
