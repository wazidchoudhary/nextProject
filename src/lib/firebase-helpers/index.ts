import { app, database } from './firebase-config';
import { getDatabase, get, child, ref, push, orderByChild, equalTo, query, limitToFirst } from 'firebase/database';
import { Order, Product } from '@/types/types';
import { ResponseParser } from '../response-parser';
import { toast } from 'react-toastify';
import { error } from 'jquery';

const dbRef = ref(database);
const sync = (entity: string) => get(child(dbRef, `${entity}/`));

export class FirebaseHelper {
    static async syncAllProducts(): Promise<Product[]> {
        const snapshot = await sync('product');
        const product = ResponseParser.parse<Product>(snapshot);

        return product;
    }

    static async fetchFeaturedProduct(): Promise<Product[]> {
        const featuredProduct = await get(query(ref(database, `product/`), ...[orderByChild('featured'), equalTo(true)]));
        const product = ResponseParser.parse<Product>(featuredProduct);

        return product;
    }

    static async fetchProductByKey(key: string, keyValue: string): Promise<Product[]> {
        const productList = await get(query(ref(database, `product/`), ...[orderByChild(key), equalTo(keyValue)]));
        const product = ResponseParser.parse<Product>(productList);

        return product;
    }

    static async fetchProductByCategory(category: string): Promise<Product[]> {
        const featuredProduct = await get(query(ref(database, `product/`), ...[orderByChild('productCategory'), equalTo(category)]));
        const product = ResponseParser.parse<Product>(featuredProduct);

        return product;
    }

    static async fetchProductByCategoryLimit(category: string, numberOfProducts: number): Promise<Product[]> {
        const featuredProduct = await get(query(ref(database, `product/`), ...[orderByChild('productCategory'), equalTo(category), limitToFirst(numberOfProducts)]));
        const products = ResponseParser.parse<Product>(featuredProduct);
        return products;
    }

    static async fetchSingleProduct(id: any): Promise<Product> {
        const snapshot = await get(child(dbRef, `product/${id}`));
        return snapshot.val();
    }
    static async sendMessage(userData: any) {
        const reference = ref(database, `messages/`);
        push(reference, userData);
        // const messages = await this.syncAllMessages();
        toast.success('Message sent Successfully.');
        // console.log('get message', messages);
    }

    static async createOrder(orderData: Order) {
        const reference = ref(database, 'orders');
        const orderStatus = push(reference, orderData)
            .then((data) => {
                toast.success('Ordered Successfully');
                return {status:true,key:data.key};
            })
            .catch((error) => {
                toast.error('Error ocurred during saving data');
                console.log(error);
                return {status:false};
            });
        console.log(orderStatus);
        return orderStatus;
    }

    static async fetchOrderDetail(id:string){
        const snapshot = await get(child(dbRef, `orders/${id}`));
        return snapshot.val();
    }

    static async syncAllMessages(): Promise<Product[]> {
        const snapshot = await sync('messages');
        const product = ResponseParser.parse<Product>(snapshot);

        return product;
    }
}
