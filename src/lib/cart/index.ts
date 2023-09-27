import useDispatch from "@/hooks/useDispatch";
import useSelector from "@/hooks/useSelector";
import store from "../store/store";
export interface Cart  {
    id:string;
    name:string;
    image:string;
    qty:number;
    price:number;
    dimension:string;
    color:string;

}
export class CartHelper {
    
    static addToCart(cartItem:Cart) : void {
   
        store.subscribe((store:any) => {
            const cart =  store['cart']
            store.dispatch({cart:[...cart,cartItem]})
            
        });
        
        
    }

    static removeFromCart(id:string) {
        store.subscribe((store:any) => {
            const cart =  store['cart']
            const index = cart.findIndex((res:Cart)=>res.id === id)
            cart.splice(index,1)
            store.dispatch({cart:[...cart]})
            localStorage.setItem('asInternationalCart',JSON.stringify([...cart]))

        });
        
    }


}