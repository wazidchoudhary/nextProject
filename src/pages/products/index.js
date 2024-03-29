import { useState, useEffect } from 'react';
import { FirebaseHelper } from '@/lib/firebase-helpers';

//Internal Imports
import styles from './Product.module.css';

import HeroSlider from '@/components/HeroSlider';
import CommonMeta from '@/seo/MetaHead';
import { Card } from '@/components/Card/card';
import { useRouter } from 'next/router';
import { FilterSection } from '../../components/FilterSection';
import { Filters } from '@/utils/sort-filter';
import BreadCrumb from '@/seo/BreadCrumb';
import ProductSchema from '@/seo/ProductSchema';
import Container from '@/components/Layout/Container';
import store from '@/lib/store/store';
import useSelector from '@/hooks/useSelector';
import { selectCartProduct } from '@/selector/cartSelector';
import { webPageSchema } from '@/seo/webPageSchema';
import { organizationSchema } from '@/seo/organizationSchema';
import { siteNavigationElement } from '@/seo/siteNavigationElement';
import { breadCrumbSchema } from '@/seo/breadCrumbSchema';

export default function ({ products }) {
    const [search, setSearch] = useState('');
    const [sorting, setSorting] = useState('');
    const [layout, setLayout] = useState('grid');
    const cartProduct = useSelector(selectCartProduct) || [];
    const cartProductIds = cartProduct.map((product) => product.id);
    const layoutClass = layout === 'grid' ? '' : 'shop-product-wrap list';
    const filterOperation = Filters(search, products, sorting);
    const router = useRouter();
    const HOST = 'https://www.teflonbonehorncrafts.com/';
    const url = 'https://www.teflonbonehorncrafts.com/products'
    const title = 'AS INTERNATIONAL - TEFLON BONE HORN HAND CRAFTS - PRODUCTS';
    const description = 'Bull Shoe Horn Supplier, Dyed Stabilized Bone Scales Exporter, Water Buffalo Horn Scale Manufacturer, Wooden Knife Handle Manufacturer, Bone Pen Blank Exporter, Bone Hair Pipe Bead Exporter In India, Drinking Horn and Mug Supplier, Wooden Cutlery Manufacturer, Teflon Bone Folder Manufacturer.';
    const breadCrumbItems = [{ url: '/', name: 'Home' }, { name: title }];

    return (
        <>
            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: webPageSchema(title, description, url) }} />
            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: organizationSchema() }} />
            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: siteNavigationElement() }} />
            <script type="application/ld+json" dangerouslySetInnerHTML={{ __html: breadCrumbSchema(title, HOST, url) }} />
            <CommonMeta title={title} description={description} />
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

            <Container>
                <FilterSection data={{ products, search, setSearch, setSorting, setLayout, layout }} />
                
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
                            addedInCart={cartProductIds.includes(p.productId)}
                        />
                    ))}
                </div>
            </Container>
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
