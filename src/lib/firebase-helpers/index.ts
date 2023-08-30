import {app, database} from './firebase-config';
import { getDatabase, get, child, ref, push, orderByChild,equalTo,query  } from "firebase/database";
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
       const featuredProduct =  await get(query(ref(database, `product/`), ...[orderByChild('productName'),equalTo('LARGE TEFLON BONE FOLDER')]));
       const projects = ResponseParser.parse<Product>(featuredProduct);
        console.log(projects)
       return projects
    }

    static async fetchSingleProduct(id:any) : Promise<Product>{
        const snapshot = await get(child(dbRef, `product/${id}`))
        return snapshot.val()
     }
}

