import Image from 'next/image';
import React from 'react';

export const EmptyProductSection = ({ width = 300, height = 300, description = 'No Product Found In This Category', fontSize = '35px' }) => {
    return (
        <>
            <section className="mt-5" style={{minHeight: '80vh'}}>
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
