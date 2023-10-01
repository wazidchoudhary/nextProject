import React from 'react';
import Link from 'next/link';
const HeroSlider = () => {
    return (
        <section className="hero-area section-margin">
            <div className="handart-slick-slider hero-slider slick-initialized slick-slider">
                <div className="slick-list draggable">
                    <div className="slick-track" style={{ opacity: 1, width: '100%', transform: 'translate3d(0px, 0px, 0px)' }}>
                        <div
                            className="single-slider hero-content bg-image slick-slide slick-current slick-active"
                            data-bg="../../public/assets/image/home-slider-image-1.webp"
                            data-slick-index={0}
                            aria-hidden="false"
                            tabIndex={0}
                            style={{ width: '100%', objectFit: 'contain', backgroundImage: 'url("assets/image/home-slider-image-1.webp")' }}
                        >
                            <div className="container position-relative">
                                <div style={{ color: 'orange', fontSize: '16px', width: '300px', marginLeft: 'auto', marginRight: 'auto' }}>Site is under development,Please visit Again</div>
                                <div className="row">
                                    <div className="col-lg-12 text-center">
                                        <h1>As International</h1>
                                        <p>
                                        Discover the elegance of AS International: Where masterful artistry in Bone, Horn, Acrylic, and Wood blends seamlessly with dependable quality, unmatched customer service, and the allure of unique masterpieces. Dive into our world of timeless beauty and handcrafted perfection.
                                        </p>
                                        <div className="slider-btn">
                                            <Link href="products/" className="btn btn-outlined" tabIndex={0}>
                                                Shop Now{' '}
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <button className="slick-next slick-arrow" style={{}} aria-disabled="false">
                <i className="ion-ios-arrow-right" />
            </button>
        </section>
    );
};

export default HeroSlider;
