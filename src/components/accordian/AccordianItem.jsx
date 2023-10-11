import { allProducts, knifeHandles } from '@/constants/navbar';
import StrUtils from '@/utils/str-utils';
import React from 'react';

export const AccordianItem = ({ category, curOpen, setCurOpen, id }) => {
    const subCategories = allProducts[category];
    const isEmpty = subCategories.length === 0;
    const isOpen = id === curOpen;
    const handleCurOpen = (id) => {
        if (id === curOpen) {
            setCurOpen(null);
        } else {
            setCurOpen(id);
        }
    };

    return (
        !isEmpty && (
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class={`accordion-button ${!isOpen && 'collapsed'}  `} type="button" onClick={() => handleCurOpen(id)}>
                        {category}
                    </button>
                </h2>
                {isOpen && (
                    <div class="accordion-collapse collapse show py-2 px-2">
                        <ul className="footer-list">
                            {subCategories.map((subCategory) => (
                                <li>
                                    <a
                                        href={`${knifeHandles[category] ? '/knifeHandles' : '/moreProducts'}/${StrUtils.normalToSnake(category)}/${StrUtils.normalToSnake(subCategory)} `}
                                        target="blank"
                                    >
                                        {subCategory}
                                    </a>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        )
    );
};
