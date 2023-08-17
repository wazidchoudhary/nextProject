import React from "react";
import Image from "next/image";
const Intro = () => {
    return (
        <section className="section-margin welcome-section" style={{marginBottom:"50px"}}>
            <div className="container">
                <div className="row justify-content-center section-padding border-bottom" style={{paddingBottom:"50px"}}>
                    <div className="col-lg-10">
                        <div className="welcome-content">
                            <h6 className="title-xs">Who We Are</h6>
                            <div className="section-title">
                                <h2>Welcome To As International</h2>
                                <div className="title-sep">
                                    <Image
                                        src={"/assets/image/IMG_20230720_114137-removebg-preview.png"}
                                        alt={`logo`}
                                        height={70}
                                        width={100}
                                        priority
                                        // quality={50}
                                    />
                                </div>
                            </div>
                            <article className="welcome-description">
                                <h4 className="sr-only">Welcome Article</h4>
                                <p>
                                    AS INTERNATIONAL is a leading name in manufacturing and exporting quality-proven range of Buffalo/Cow
                                    Horn and Camel Bone Material including Buffalo Horn Knife Handle Material, Camel Bone Knife Handle
                                    Material, Bone Crafts, Horn Craft, Buffalo Horn Plates for Optical Frame, Bone Buttons, Bone Pendants,
                                    Hair Bone Needle etc. Our products are manufactured using advanced technological machines and
                                    quality-approved raw material & components. Our state-of-the-art infrastructure enables us to cater
                                    various demands and requirements of the prestigious patrons, located across diversified application
                                    areas. The infrastructure within our organizational premises is segregated into various departments for
                                    smooth functioning of all business activities. We have also set a manufacturing unit, which is
                                    well-equipped with technological advanced machines to increase production capacity. Experienced
                                    professionals and skilled workforce enable us to meet urgent and bulk requirements of the clients. Our
                                    transparent and ethical business policies help us in establishing trust and faith of the clients. We
                                    hope you enjoy our products as much as we enjoy offering them to you. If you have any questions or
                                    comments, please don’t hesitate to contact us.
                                </p>
                            </article>
                            <div className="author-block">
                                <a>  
                                    <span className="font-weight-mid text-black text-uppercase">Shakulat Choudhary</span> - CEO
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
