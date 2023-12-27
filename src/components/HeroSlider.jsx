import React, { useEffect, useState } from 'react';
import useAfterPageLoad from '@/hooks/useAfterPageLoad';

const HeroSlider = () => {
    const [bgImage, setBgImage] = useAfterPageLoad('url("assets/image/hero.webp")', 'url("https://img.freepik.com/free-vector/white-blurred-background_1034-249.jpg?w=2000")');
    const backgroundImages = [
        "url('assets/image/hero.webp')",
        "url('assets/image/1.webp')",
        "url('assets/image/2.webp')",
        "url('assets/image/3.webp')",
        "url('assets/image/4.webp')"

        // Add more image URLs as needed
      ];
    
      const [currentBackground, setCurrentBackground] = useState(backgroundImages[0]);
      const [index, setIndex] = useState(0);
    
      useEffect(() => {
        const intervalId = setInterval(() => {
            // Fade out current image
            let slider = document.querySelector('.single-slider');
            slider.classList.add('fade-out');
      
            // After fade out, change image and fade in
            setTimeout(() => {
              const nextIndex = (index + 1) % backgroundImages.length;
              setCurrentBackground(backgroundImages[nextIndex]);
              setIndex(nextIndex);
              slider.classList.remove('fade-out');
              slider.classList.add('fade-in');
            }, 500); // This should match the transition time
      
          }, 5000);// Change every 3 seconds
    
        return () => clearInterval(intervalId); // Clean up interval on component unmount
      }, [index, backgroundImages]);
    
    return (
        <section className="hero-area section-margin">
            <div className="handart-slick-slider hero-slider-2 slick-initialized slick-slider">
                <div className="slick-list draggable">
                    <div className="slick-track" style={{ opacity: 1, width: '100%', transform: 'translate3d(0px, 0px, 0px)' }}>
                        <div
                            className="single-slider hero-content bg-image"
                            style={{ width: '100%', objectFit: 'cover', backgroundImage: currentBackground , transition:'background-image 0.5s ease-in-out'}}
                        >
                            {/* <img src="assets/image/hero.webp"/> */}
                            <div className="container position-relative">
                                <div className="row">
                                    <div className="col-lg-12 hero-slider-custom">
                                    <h1 className='d-none'>AS INTERNATIONAL</h1>
                                        <h2 className='outlined-text'>
                                            AS <br /> INTERNATIONAL
                                        </h2>
                                        <p className="outlined-text">
                                            Discover the elegance of AS International: Where masterful artistry in Bone, Horn, Acrylic, and Wood blends seamlessly with dependable quality, unmatched
                                            customer service, and the allure of unique masterpieces. Dive into our world of timeless beauty and handcrafted perfection.
                                        </p>
                                        <div className="slider-btn">
                                            <a title='Shop Now' href="/products" className="btn btn-outlined" tabIndex={0}>
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
