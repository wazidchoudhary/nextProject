import Image from 'next/image';
import React from 'react';

export const EmptyProductSection = ({ width = 300, height = 300, description = 'No Product Found In This Category', fontSize = '35px', containerHeight='80vh' }) => {
    return (
        <>
            <section className="mt-5 display-center" style={{minHeight: containerHeight}}>
                <div className="container">
                    <div className="section-title mb--55">
                        <Image src={'/assets/image/no-product.png'} alt="No Product Found" width={width} height={height} />
                        <h2 style={{ fontSize: fontSize }}>{description}</h2>
                    </div>
                </div>
            </section>
        </>
    );
};
