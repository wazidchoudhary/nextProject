import React, { useState } from 'react';
// Ensure this path is correct

const Carousel = ({ slides }) => {
  const [currentSlide, setCurrentSlide] = useState(0);

  const nextSlide = () => {
    setCurrentSlide(currentSlide === slides.length - 1 ? 0 : currentSlide + 1);
  };

  const prevSlide = () => {
    setCurrentSlide(currentSlide === 0 ? slides.length - 1 : currentSlide - 1);
  };

  return (
    <div className="carousel" style={{width:'100%', backgroundColor:'blue',overflow:'hidden'}}>
      
      <button className="carousel-control-prev" onClick={prevSlide}>
        &#10094;
      </button>
      <button className="carousel-control-next" onClick={nextSlide}>
        &#10095;
      </button>
    </div>
  );
};

export default Carousel;
