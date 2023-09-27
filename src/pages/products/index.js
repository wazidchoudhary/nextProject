import { useState,useEffect } from 'react';
import { FirebaseHelper } from '@/lib/firebase-helpers';

//Internal Imports
import styles from './Product.module.css';

import HeroSlider from '@/components/HeroSlider';
import MetaHead from '@/components/MetaHead';
import { Card } from '@/components/card';
import { useRouter } from 'next/router';
import { FilterSection } from '../../components/FilterSection';
import { Filters } from '@/utils/sort-filter';
import store from '@/lib/store/store';
export default function ({ products }) {
    const [search, setSearch] = useState('');
    const [sorting, setSorting] = useState('');

    const filterOparetion = Filters(search, products, sorting);

    const router = useRouter();
    useEffect(()=>{
        const fetchedFromLocalStorage = localStorage.getItem('asInternationalCart')
        store.dispatch({
            cart: fetchedFromLocalStorage ? JSON.parse(fetchedFromLocalStorage) : [] 
        });
    },[])
    return (
        <>
            <MetaHead title="Products" description="best products" />
            <section className="bg-image">
                <h2 className="sr-only">Site Breadcrumb</h2>
                <div className="container" style={{ height: '150px' }}></div>
            </section>
            <section className="section-padding">
                <FilterSection data={{ products, search, setSearch, setSorting }} />

                <div className="container">
                    <div className="ha-custom-tab">
                        <div className="tab-content space-db--30" id="myTabContent">
                            <div className="tab-pane fade show active" id="shop" role="tabpanel" aria-labelledby="shop-tab">
                                <div className="row">
                                    {filterOparetion.map((p) => (
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
                                            }}
                                            handleClick={(id) => {
                                                router.push(`/products/${id}`);
                                            }}
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
