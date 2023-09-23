import {app, database} from './firebase-config';
import { getDatabase, get, child, ref, push, orderByChild,equalTo,query, limitToFirst  } from "firebase/database";
import { Product } from '@/types/types';
import { ResponseParser } from '../response-parser';

const dbRef = ref(database);
const sync = (entity: string) => get(child(dbRef, `${entity}/`));

export class FirebaseHelper {
    static async syncAllProducts(): Promise<Product[]> {
        const snapshot = await sync('product');
        const projects = ResponseParser.parse<Product>(snapshot);
        
        return projects
    }

    static async fetchFeaturedProduct(): Promise<Product[]>{
       const featuredProduct =  await get(query(ref(database, `product/`), ...[orderByChild('featured'),equalTo(true)]));
       const projects = ResponseParser.parse<Product>(featuredProduct);
        
       return projects
    }

    static async fetchProductByCategory(category:string) : Promise<Product[]>{
        const featuredProduct =  await get(query(ref(database, `product/`), ...[orderByChild('productCategory'),equalTo(category)]));
        const projects = ResponseParser.parse<Product>(featuredProduct);
       
       return projects
    }

    static async fetchProductByCategoryLimit(category:string,numberOfProducts:number) : Promise<Product[]>{
        const featuredProduct =  await get(query(ref(database, `product/`), ...[orderByChild('productCategory'),equalTo(category),limitToFirst(numberOfProducts)]));
        const products = ResponseParser.parse<Product>(featuredProduct);
        return products
    }

    static async fetchSingleProduct(id:any) : Promise<Product>{
        const snapshot = await get(child(dbRef, `product/${id}`))
        return snapshot.val()
     }
}

