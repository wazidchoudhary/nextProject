import React from 'react';
import CommonMeta from '@/seo/MetaHead';
import Image from 'next/image';
export default function () {
    return (
        <>
            <CommonMeta title="About Us - AS INTERNATIONAL" description="AS INTERNATIONAL COMPANY PROFILE" />
            <main className="contact_area section-padding pt--40">
                <div className="container-lg pt-5">
                    <div className="section-title" style={{ backgroundColor: '#f6f6f6', padding: '50px' }}>
                    <h1 className='d-none'>About Us</h1>
                        <Image src="/assets/image/logo.webp" alt="As International Logo" width={150} height={100} />
                        <div className="row mt--30" style={{ textAlign: 'center' }}>
                            <h3 style={{ color: 'black' }}>Introducing AS International: Crafting Elegance from Nature's Gifts</h3>
                        </div>
                        <article className="aboutArticle" style={{ textAlign: 'justify' }}>
                            <p>
                                At AS International, craftsmanship thrives in its purest form, embodied by our team of artisans who are not only skilled craftsmen but also genuine manufacturers. Our
                                passion lies in the transformation of natural materials – buffalo and sheep horn, camel and buffalo bone, and a diverse array of woods – into exquisite creations that
                                stand as testaments to artistry and functionality.
                            </p>
                            <h5>Unveiling the Artistry</h5>
                            <p>
                                Our journey begins with raw materials that hold stories of their own. We specialize in creating an array of products, each a testament to the elegance found in nature's
                                offerings. From these natural treasures, we meticulously craft a variety of scales, each destined to grace knife handles across the globe. Our dedication to perfection
                                ensures that every scale tells a story of precision and dedication.
                            </p>
                            <h5>A Symphony of Sound and Art</h5>
                            <p>
                                In the world of music, AS International plays a vital role. Our horn and bone nuts and saddles become an integral part of crafting guitars that resonate with beauty and
                                harmony. Each nut and saddle carries the mark of our commitment to enhancing musical experiences, blending aesthetics with functionality.
                            </p>
                            <h5>Buttons: Where Elegance Meets Utility</h5>
                            <p>
                                Buttons aren't just functional; they're expressions of style. At AS International, we craft buttons from bone, horn, and wood, each a miniature masterpiece that adds a
                                touch of sophistication to garments. Our buttons are more than closures; they're statements of taste and refinement that adorn apparel around the world.
                            </p>
                            <h5>Beads: Crafting Stories of Adornment</h5>
                            <p>
                                Jewelry is more than an accessory; it's a reflection of personality and sentiment. Our journey takes us to the creation of unique beads, where bone, horn, and wood fuse
                                into captivating designs. Each bead is a vessel of creativity, an instrument that artisans use to weave tales of beauty around necks and wrists.
                            </p>
                            <h5>Bone Folders: Binding Stories, Crafting Legacies</h5>
                            <p>
                                In the world of literature, AS International has its mark. Our bone folders are essential tools in the hands of bookbinders, preserving knowledge and artistry for
                                generations. With every fold, our bone folders become partners in creating enduring works that stand as a testament to the power of craftsmanship.
                            </p>
                            <p>
                                At AS International, we don't just craft products; we weave stories. Our commitment to perfection, our reverence for nature's gifts, and our dedication to artistry come
                                together in every creation we offer. Join us in celebrating the marriage of nature and skill, where raw materials transform into exquisite works of art, and utility
                                merges with elegance. AS International: Crafting the Extraordinary, One Piece at a Time.
                            </p>
                            <p>The factory of the company is based in Uttar Pradesh India. The buyers are welcomed to pay a visit to the factory.</p>{' '}
                        </article>
                    </div>
                </div>
            </main>
        </>
    );
}
