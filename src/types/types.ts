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

export interface Customer {
    name: string;
    companyName?: string;
    country: string;
    email: string;
    mobile: string;
    addressLine1: string;
    addressLine2: string;
    city: string;
    state: string;
    zipCode: string;
    orderNote?: string;
}

export interface Cart {
    name: string;
    id: number;
    dimension?: string;
    color?: string;
    image: string;
    price: number;
    qty: number;
}

export interface Order {
    orderId: string;
    shippingDetail: Customer;
    products: Array<Cart>;
    time:string;
}
