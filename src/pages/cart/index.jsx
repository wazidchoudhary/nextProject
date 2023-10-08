import useSelector from '@/hooks/useSelector';
import { selectCartProduct } from '@/selector/cartSelector';
import { CartHelper } from '@/lib/cart';
import BreadCrumb from '@/seo/BreadCrumb';
import React from 'react';
import products from '../products';


export default function () {
  const cartProducts = useSelector(selectCartProduct) || []
  console.log(cartProducts)

  const totalPrice=cartProducts.reduce((previousValue, currentValue) => {
    return previousValue + currentValue.qty * currentValue.price
}, 0)


  const title = 'Cart'
  const breadCrumbItems = [{ url: '/', name: 'Home' }, { name: title }];
  return <>
    <BreadCrumb items={breadCrumbItems} text={title} />

    {/* Cart Page Start */}
    <main className="cart-page-main-block inner-page-sec-padding-top">
      <div className="cart_area cart-area-padding sp-inner-page--top ">
        <div className="container">
          <div className="page-section-title">
            <h1>SHOPPING CART</h1>
          </div>
          <div className="row">
            <div className="col-12">
              <form action="#" className>
                {/* Cart Table */}
                <div className="cart-table table-responsive mb--40">
                  <table className="table">
                    {/* Head Row */}
                    <thead>
                      <tr>

                        <th className="pro-remove" />
                        <th className="pro-thumbnail">Image</th>
                        <th className="pro-title">Product</th>
                        <th className="pro-price">Price</th>
                        <th className="pro-quantity">Quantity</th>
                        <th className="pro-subtotal">Total</th>
                      </tr>
                    </thead>
                    <tbody>
                      {/* Product Row */}

                      {cartProducts.map((product, j) => {
                        return <tr key={j} ><td className="pro-remove"><a href="cart.html#"><i className="far fa-trash-alt" /></a></td>
                          <td className="pro-thumbnail"><img src={product.image} alt="Product" />
                          </td>
                          <td className="pro-title">{product.name}</td>
                          <td className="pro-price"><span>${product.price}</span></td>
                          <td className="pro-quantity">
                            <div className="pro-qty">
                              <div className="count-input-block">
                                <input type="number" className="form-control text-center" defaultValue={product.qty} min={1} />
                              </div>
                            </div>
                          </td>
                          <td className="pro-subtotal"><span>${product.price}</span></td></tr>

                      })}


                      {/* Product Row */}

                      {/* Discount Row  */}
                      {/* <tr>
                        <td colSpan={6} className="actions">
                          <div className="coupon-block">
                            <div className="coupon-text">
                              <label htmlFor="coupon_code">Coupon:</label>
                              <input type="text" name="coupon_code" className="input-text" id="coupon_code" defaultValue placeholder="Coupon code" />
                            </div>
                            <div className="coupon-btn">
                              <input type="submit" className="btn-black" name="apply_coupon" defaultValue="Apply coupon" />
                            </div>
                          </div>
                          <div className="update-block text-end">
                            <input type="submit" className="btn-black" name="update_cart" defaultValue="Update cart" />
                            <input type="hidden" id="_wpnonce" name="_wpnonce" defaultValue="05741b501f" /><input type="hidden" name="_wp_http_referer" defaultValue="/petmark/cart/" />
                          </div>
                        </td>
                      </tr> */}
                    </tbody>
                  </table>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>
      <div className="cart-section-2 sp-inner-page--bottom">
        <div className="container">
          <div className="row">

            {/* Cart Summary */}
            <div className="col-lg-6 col-12 d-flex">
              <div className="cart-summary">
                <div className="cart-summary-wrap">
                  <h4><span>Cart Summary</span></h4>


                  <p>Sub Total <span className="text-primary">${totalPrice}</span></p>
                  <p>Shipping Cost <span className="text-primary">$00.00</span></p>
                  <h2>Grand Total <span className="text-primary">${totalPrice}</span></h2>
                </div>
                <div className="cart-summary-button" style={{ marginBottom: '10px' }}>
                  <a href="checkout.html" className="checkout-btn c-btn">Checkout</a>
                  <button className="update-btn c-btn">Update Cart</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
    {/* Cart Page End */}

  </>;
}
