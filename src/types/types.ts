export interface PriceCategory {
    id: string;
    name: string;
}

export interface ProductCategory {
    category: string;
    categoryId: string;
}

export interface PriceType {
    price: string;
    type: string;
}

export interface Product {
    productName: string;
    productId: Number;
    productCategory: ProductCategory;
    productSubCategory: string;
    productQty: string;
    productPrice: string | Array<PriceType>;
    productOldPrice?: string;
    productImage: string[];
    productDescription: string;
    productColor?: string;
    productType?: string;
    productSize?: string;
}
