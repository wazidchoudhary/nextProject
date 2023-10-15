import useDispatch from '@/hooks/useDispatch';
import useSelector from '@/hooks/useSelector';
import store from '../store/store';
import { toast } from 'react-toastify';
export interface Cart {
    qty: number;
    price: number;
    dimension: string;
    color: string;
}
export class CartHelper {
    static addToCart({ productId, productName, productImage }: any, prod: any): void {
        const cartData = store.get('cart');
        const index = cartData.findIndex((prod: any) => prod.id == productId);
        if (index != -1) {
            cartData[index].qty++;
            store.dispatch({ cart: [...cartData] });
            localStorage.setItem('asInternationalCart', JSON.stringify([...cartData]));
        } else {
            const cartProduct = {
                id: productId,
                name: productName,
                image: productImage[0],
                qty: prod.quantity,
                price: parseFloat(prod.selectedPrice),
                dimension: prod.selectedDimension,
                color: prod.selectedColor,
            };

            store.dispatch({ cart: [...cartData, cartProduct] });
            localStorage.setItem('asInternationalCart', JSON.stringify([...cartData, cartProduct]));
        }

        toast.success('Product added successfully ');
    }

    static removeFromCart(id: string) {
        const cart = store.get('cart');
        const updatedCart = cart.filter((prod: any) => prod.id !== id);
        store.dispatch({ cart: updatedCart });
        localStorage.setItem('asInternationalCart', JSON.stringify(updatedCart));
    }

    static emptyCart() {
        store.dispatch({ cart: [] });
        localStorage.setItem('asInternationalCart', JSON.stringify([]));
    }
}
