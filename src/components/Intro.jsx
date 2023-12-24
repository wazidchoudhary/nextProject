import React from 'react';
import Image from 'next/image';
import Ellipsis from './Ellipsis';
const Intro = () => {
    return (
        <section className="section-margin welcome-section jumbotron" id="intro" style={{ marginBottom: '50px' }}>
            <div className="container">
                <div className="row justify-content-center section-padding border-bottom" style={{ paddingBottom: '50px' }}>
                    <div className="col-lg-10">
                        <div className="welcome-content">
                            <h6 className="title-xs">Who We Are</h6>
                            <div className="section-title">
                                <h2>Welcome To AS International</h2>
                                <div className="title-sep">
                                    <Image
                                        title='Intro Logo'
                                        src={'/assets/image/logo.webp'}
                                        alt={`logo`}
                                        height={100}
                                        width={100}
                                        priority
                                        // quality={50}
                                        style={{ objectFit: 'contain' }}
                                    />
                                </div>
                            </div>
                            <article className="welcome-description">
                                <h4 className="sr-only">Welcome Article</h4>
                                <Ellipsis
                                    text={`"Welcome to AS INTERNATIONAL, a distinguished leader in the realm of manufacturing and globally exporting an exemplary assortment of Buffalo/Cow Horn and Camel Bone
                                    materials. Our range encompasses an array of top-tier products, including Buffalo Horn Knife Handle Material, Camel Bone Knife Handle Material, Bone Crafts, Horn
                                    Craft, Buffalo Horn Plates for Optical Frames, Bone Buttons, Bone Pendants, Hair Bone Needles, and more. At AS INTERNATIONAL, we take pride in crafting our products
                                    with utmost precision and finesse, harnessing the capabilities of cutting-edge technological machinery. We source only the finest, quality-approved raw materials
                                    and components, ensuring that every item that leaves our facility upholds the highest standards of excellence. Our avant-garde infrastructure stands as a testament
                                    to our commitment to excellence. Strategically segmented into specialized departments, it orchestrates the seamless orchestration of our diverse business
                                    operations. Central to this is our advanced manufacturing unit, a hub of technological innovation equipped with state-of-the-art machinery that fuels our enhanced
                                    production capacity. The heart of our success lies in the expertise of our proficient professionals and skilled workforce. Their experience enables us to not only
                                    fulfill urgent requirements but also meet the demands of bulk orders without compromise. By adhering to transparent and ethical business practices, we have
                                    cultivated a relationship of trust and reliability with our clients. As connoisseurs of our craft, we derive immense pleasure from presenting our products to you.
                                    We invite you to indulge in the experience of our offerings, which we are confident you will enjoy as much as we enjoy bringing them to you. If you have inquiries,
                                    curiosities, or suggestions, we encourage you to reach out to us without hesitation. Your engagement is invaluable to us as we continue to push the boundaries of
                                    excellence in our domain."`}
                                />
                            </article>
                            <div className="author-block">
                                <a title='As International' href='/home'>
                                    <span className="font-weight-bold text-black text-uppercase">Shakulat Choudhary</span> - CEO
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
};

export default Intro;
