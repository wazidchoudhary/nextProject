import React from 'react'

const Intro = () => {
  return (
 <section className="section-margin welcome-section">
  <div className="container">
    <div className="row justify-content-center section-padding border-bottom">
      <div className="col-lg-8">
        <div className="welcome-content">
          <h6 className="title-xs">Who We Are</h6>
          <div className="section-title">
            <h2>Welcome To Handart</h2>
            <div className="title-sep">
              <img src="image/icon/title-sep-icon.png" alt />
            </div>
          </div>
          <article className="welcome-description">
            <h4 className="sr-only">Welcome Article</h4>
            <p>Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie
              consequat, vel illum dolore eu feugiat
              nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit
              praesent luptatum zzril delenit augue
              duis
              dolore te feugait nulla facilisi.</p>
          </article>
          <div className="author-block">
            <a href="index.html#"> <span className="font-weight-mid text-black text-uppercase">Jhon doe</span> -
              CEO Handart</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

  )
}

export default Intro