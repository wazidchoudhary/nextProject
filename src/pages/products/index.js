import { useState, useEffect } from 'react';
import { FirebaseHelper } from '@/lib/firebase-helpers';

//Internal Imports
import styles from './Product.module.css';

import HeroSlider from '@/components/HeroSlider';
import MetaHead from '@/seo/MetaHead';
import { Card } from '@/components/Card/card';
import { useRouter } from 'next/router';
import { FilterSection } from '../../components/FilterSection';
import { Filters } from '@/utils/sort-filter';
import BreadCrumb from '@/seo/BreadCrumb';
import ProductSchema from '@/seo/ProductSchema';

export default function ({ products }) {
    const [search, setSearch] = useState('');
    const [sorting, setSorting] = useState('');
    const [layout, setLayout] = useState('grid');
    const layoutClass = layout === 'grid' ? '' : 'shop-product-wrap list';
    const filterOperation = Filters(search, products, sorting);
    const router = useRouter();
    const title = 'Products';
    const description = 'Teflon Bone horn Materials';
    const breadCrumbItems = [{ url: '/', name: 'Home' }, { name: title }];
    return (
        <>
            <MetaHead title={title} description={description} />
            <BreadCrumb items={breadCrumbItems} text={title} />
            {products.map((p) => (
                <ProductSchema
                    product={{
                        id: p.productId,
                        name: p.productName,
                        image: p.productImage,
                        description: p.productDescription,
                        category: p.productCategory,
                        price: p.productPrice,
                        oldPrice: p.productOldPrice,
                    }}
                />
            ))}

            <section className="section-padding">
                <div className="container pt-5">
                    <FilterSection data={{ products, search, setSearch, setSorting, setLayout, layout }} />
                    <div className="ha-custom-tab">
                        <div className="row space-db--30" id="myTabContent">
                            <div className="tab-pane fade show active" id="shop" role="tabpanel" aria-labelledby="shop-tab">
                                <div className={`row ${layoutClass}`}>
                                    {filterOperation.map((p) => (
                                        <Card
                                            key={p.productId}
                                            content={{
                                                id: p.productId,
                                                category: p.productCategory,
                                                subCategory: p.productSubCategory,
                                                name: p.productName,
                                                priceNew: p.productPrice,
                                                priceOld: p.productOldPrice,
                                                image: p.productImage,
                                                color: p.productColor,
                                                description: p.productDescription,
                                            }}
                                            handleClick={(id) => {
                                                router.push(`/products/${id}`);
                                            }}
                                            layout={layout}
                                        />
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </>
    );
}

export const getServerSideProps = async (context) => {
    const products = await FirebaseHelper.syncAllProducts();
    return {
        props: {
            products: products,
        },
    };
};
