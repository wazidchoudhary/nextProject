import Image from 'next/image';
import React from 'react';

export const EmptyProductSection = () => {
    return (
        <>
            <section className="mt-5">
                <div className="container">
                    <div className="section-title mb--55">
                        <Image src={'/assets/image/no-product.png'} alt="No Product Found" width={300} height={300} />
                        <h2>No Product Found In This Category</h2>
                    </div>
                </div>
            </section>
        </>
    );
};
