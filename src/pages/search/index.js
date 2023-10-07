import { FirebaseHelper } from '@/lib/firebase-helpers';
import MetaHead from '@/seo/MetaHead';
import BreadCrumb from '@/seo/BreadCrumb';
import Container from '@/components/Layout/Container';
import { Fragment, useEffect, useState } from 'react';
import { EmptyProductSection } from '@/components/EmptyProductSection';
import * as Constants from '@/constants/navbar';
import { Card } from '@/components/Card/card';
import { useRouter } from 'next/router';
import useSelector from '@/hooks/useSelector';
import { selectCartProduct } from '@/selector/cartSelector';

export default function ({}) {
    const router = useRouter();
    const [userInput, setUserInput] = useState('');
    const [products, setProducts] = useState([]);
    const [filteredProducts, setFilteredProducts] = useState([]);
    const [selectedCategory, setSelectedCategory] = useState('');
    const cartProduct = useSelector(selectCartProduct) || [];
    const cartProductIds = cartProduct.map((product) => product.id);
    const allCategories = [...Object.keys(Constants.knifeHandles), ...Object.keys(Constants.products)].sort();
    const title = selectedCategory || 'Search';
    const description = 'Teflon Bone horn Materials';
    const breadCrumbItems = [{ url: '/', name: 'Home' }, { name: 'Search' }];

    useEffect(() => {
        (async () => {
            const products = await FirebaseHelper.syncAllProducts();
            console.log(products, ' @@@prod');
            setProducts(products);
        })();
    }, []);

    const hasMatchedKeywords = (str1, str2) => {
        if (str2.indexOf(str1) !== -1) return true;
        return false;
    };

    useEffect(() => {
        if (userInput) {
            const uprCase = userInput.toUpperCase();
            let filtered = products.filter((prod) => {
                const title = prod.productName.toUpperCase();
                const description = (prod.productDescription || '').toUpperCase();
                const color = (prod.productColor || '').toUpperCase();
                const category = (prod.productCategory || '').toUpperCase();

                return hasMatchedKeywords(uprCase, title) || hasMatchedKeywords(uprCase, description) || hasMatchedKeywords(uprCase, color) || hasMatchedKeywords(uprCase, category);
            });
            
            if(selectedCategory) {
                filtered = filtered.filter(p => {
                    const category = (p.productCategory || '').toUpperCase();
                    if(selectedCategory === category) {
                        return p;
                    }
                })
            }

            setFilteredProducts(filtered)
        } else {
            setFilteredProducts([])
        }
    }, [userInput, selectedCategory]);

    return (
        <>
            <MetaHead title={title} description={description} />
            <BreadCrumb items={breadCrumbItems} text={title} />
            <Container>
                <div className="row">
                    <div className="col-12 col-md-8">
                        <input type="text" onChange={(e) => setUserInput(e.target.value || '')} className="form-control my-2" placeholder="Search..." />
                    </div>
                    <div className="col-12 col-md-4">
                        <select className="form-control my-2" onChange={(e) => setSelectedCategory(e.target.value || '')}>
                            <option value="">All Categories</option>
                            {allCategories.map((cat, idx) => (
                                <option key={idx} value={cat}>
                                    {cat}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>
                {filteredProducts.length > 0 && (
                   <Fragment>
                   <h4 className='my-3' style={{margin: '20px 0'}}>Products ({filteredProducts.length})</h4>
                   <div className={`row`}>
                        {filteredProducts.map((p) => (
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
                                addedInCart={cartProductIds.includes(p.productId)}
                            />
                        ))}
                    </div>
                   </Fragment>
                )}
                {filteredProducts.length === 0 && <EmptyProductSection containerHeight="auto" />}
            </Container>
        </>
    );
}
