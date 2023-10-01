import React from 'react';
import Link from 'next/link';

const HeroSlider = () => {
    return (
        <section className="hero-area section-margin">
            <div
                className="handart-slick-slider hero-slider-2 slick-initialized slick-slider"
                data-slick-setting='{
  "autoplay": true,
  "autoplaySpeed": 8000,
  "slidesToShow": 1,
  "arrows": true,
  "prevArrow":{"buttonClass": "slick-prev","iconClass":"ion-ios-arrow-left"},
  "nextArrow":{"buttonClass": "slick-next","iconClass":"ion-ios-arrow-right"}
    }'
                data-slick-responsive='[
                      {"breakpoint":480, "settings": {"arrows": false} }
                  ]'
            >
                <div className="slick-list draggable">
                    <div className="slick-track" style={{ opacity: 1, width: '100%', transform: 'translate3d(0px, 0px, 0px)' }}>
                        <div
                            className="single-slider hero-content bg-image slick-slide slick-current slick-active"
                            data-bg="image/bg-images/home-2/home-slider-image-1.jpg"
                            data-slick-index={0}
                            aria-hidden="false"
                            tabIndex={0}
                            style={{ width: '100%', objectFit:'contain', backgroundImage: 'url("assets/image/hero.webp")' }}
                        >
                            {/* <img src="assets/image/hero.webp"/> */}
                            <div className="container position-relative">
                                <div className="row">
                                    <div className="col-lg-12 hero-slider-custom">
                                        <h2>
                                            AS <br /> INTERNATIONAL
                                        </h2>
                                        <p className="">
                                            Discover the elegance of AS International: Where masterful artistry in Bone, Horn, Acrylic, and Wood blends seamlessly with dependable quality, unmatched
                                            customer service, and the allure of unique masterpieces. Dive into our world of timeless beauty and handcrafted perfection.
                                        </p>
                                        <div className="slider-btn">
                                            <a href="/products" className="btn btn-outlined" tabIndex={0}>
                                                Shop Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <span className="herobanner-progress" />
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
};

export default HeroSlider;
