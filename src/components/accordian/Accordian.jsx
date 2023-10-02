import React, { useState } from 'react';
import { AccordianItem } from './AccordianItem';
import { allProducts } from '@/constants/navbar';

const productsCategories = Object.keys(allProducts);
console.log(allProducts);

export const Accordian = () => {
    const [curOpen, setCurOpen] = useState(null);
    return (
        <div className="container mb-5">
            <div class=" accordion" id="accordionExample">
                {productsCategories.map((category, id) => (
                    <AccordianItem key={category} id={`accordian-${id}`} category={category} curOpen={curOpen} setCurOpen={setCurOpen} />
                ))}
            </div>
        </div>
    );
};
