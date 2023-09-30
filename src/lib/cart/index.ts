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
        const cart = store.get('cart')
        store.dispatch({cart:[...cart,cartItem]}) 
    }

    static removeFromCart(id:string) {
            const cart =  store.get('cart')
            const updatedCart  = cart.filter((prod:Cart) => prod.id !== id)
            store.dispatch({cart:updatedCart})
            localStorage.setItem('asInternationalCart',JSON.stringify(updatedCart))
    }


}