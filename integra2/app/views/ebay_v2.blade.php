<html>
<meta name="viewport" content="user-scalable=no, initial-scale=1, maximum-scale=1, minimum-scale=1, width=device-width, height=device-height" />

<style type="text/css">
  /*Styles for simulate toggle button with CSS */
  input[name='toggler'] {
    display: none;
  }
  input[name='toggler']:checked + .step-dot + .step-content {
    display: block;
  }
  input[name='toggler']:checked + .step-dot:after {
    background: #219150;
    color: #fff;
  }
  input[name='toggler']:checked ~ input:checked + .step-dot:before {
    background: #219150;
  }
  input[name='toggler']:checked ~ input:not(:checked) + .step-dot {
    color: #888;
  }
  input[name='toggler']:checked ~ input:not(:checked) + .step-dot:after {
    border-color: #888;
    color: #888;
  }

  img {
    max-width: 100%;
  }

  .readmo {
    border: 2px solid #e26f0f;
    
    padding: 0px 0 10px;
    background: white;
    position: relative;
    min-height: 250px;
  }
  .readmo p {
    padding: 0px 4px 0px;
    margin-bottom: 10px;
    max-height: 5000px;
    overflow: hidden;
    transition: max-height 0.1s ease-in;
  }
  .readmo label {
    display: block;
    cursor: pointer;
    text-align: center;
    padding: 0px 0 0px;
    margin: 0px 0 0 0;
    position: absolute;
    width: 100%;
    bottom: -8px;
    background: linear-gradient(180deg, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0) 5%, rgba(255, 255, 255, 0.94) 95%, white 100%);
    /* w3c */
  }
  .readmo label:after {
    content: "\203A";
    color: #e26f0f;
    position: relative;
    width: 100%;
    font-size: 41px;
    padding: 0;
    display: block;
    transform: rotate(270deg);
  }
  .readmo label:hover:after {
    color: #333;
  }
  .readmo input[type=checkbox] {
    display: none;
  }
  .readmo input[type=checkbox]:checked + label:after {
    transform: rotate(90deg);
  }
  .readmo input[type=checkbox]:checked ~ p {
    max-height: 205px;
    transition: max-height 0.1s ease-in;
  }
</style>

<style>
    /* For desktop: */
    .col-1 {width: 8.33%;}
    .col-2 {width: 16.66%;}
    .col-3 {width: 25%;}
    .col-4 {width: 33.33%;}
    .col-5 {width: 41.66%;}
    .col-6 {width: 50%;}
    .col-7 {width: 58.33%;}
    .col-8 {width: 66.66%;}
    .col-9 {width: 75%;}
    .col-10 {width: 83.33%;}
    .col-11 {width: 91.66%;}
    .col-12 {width: 100%;}
    
    @media (min-width: 769px) {
      .hide{
        display: none;
      }
      .qe.label{
        white-space: nowrap;
      }
      .center-page { margin-left: auto; margin-right: auto; display: table }
      .push-left {float: left}
    }

    @media (max-width: 768px) {
      /* For mobile phones: */
      .hide-xs {
          display: none;
      }
      
      .bold{
        font-weight: bold;
      }
      
      .show-xs {
        display: 
      }

      .vehicle_compability p {
        font-size: 22px;
      }

      .col-1 {width: 100%;}
      .col-2 {width: 100%;}
      .col-3 {width: 100%;}
      .col-4 {width: 100%;}
      .col-5 {width: 100%;}
      .col-6 {width: 100%;}
      .col-7 {width: 100%;}
      .col-8 {width: 100%;}
      .col-9 {width: 100%;}
      .col-10 {width: 100%;}
      .col-11 {width: 100%;}
      .col-12 {width: 100%;}

    }

    .qe td {font-family: Arial; text-align: left; vertical-align: top; border-radius: 0.5em; border-color: #bc5917}
    .qe .center {text-align: center}
    .qe .vcenter {vertical-align: middle;}

    .qe .label {background-color: #e26f0f; font-weight: bolder; color: white; width: 1px; text-shadow: 2px 2px 4px #000000; box-shadow: 2px 2px 4px black; -webkit-box-shadow: 2px 2px 4px black; -moz-box-shadow: 2px 2px 4px black}
    .qe .indent {/*padding-left: 30px*/}
    .qe .fit {width: 1px; white-space: nowrap}
</style>
</html>
<body>
<table class="qe center-page" cellpadding="5px" cellspacing="8x">
    <tr>
        <td class="center"><img src="https://qeautoparts.com/ebay/qelogo.gif"></td>
    </tr>
</table>
<div class="center-page">
    <table class="qe push-left col-9" cellpadding="5px" cellspacing="8x">
        <tr >
            <td colspan="4" class="label center bold">{{$title}}</td>
        </tr>
        <tr>
            <td class="label">Description</td>
            <td colspan="3" class="bold">{{ implode('<br>', $desc) }}</td>
        </tr>
        <tr>
            <td class="label center">Brand</td>
            <td class="bold">{{{ $brand }}}</td>
            <td class="label fit hide-xs">Condition</td>
            <td class="fit hide-xs">{{{ $condition }}}</td>
        </tr>
        <tr class='hide'>
            <td class="label fit center">Condition</td>
            <td class="fit bold">{{{ $condition }}}</td>
        </tr>
        <tr>
            <td class="label center vcenter">Part Number</td>
            <td colspan="3" class="bold">{{{ implode(' / ', array_keys($mpns)) }}}</td>
        </tr>
        <tr>
            <td class="label center vcenter">Notes</td>
            <td colspan="3" class="bold">{{ implode('<br>', $notes) }}</td>
        </tr>
        <tr class="hide">
            <td colspan="4" class="label">Vehicle Compatibility</td>
        </tr>
        <tr class="hide vehicle_compability">
            <td colspan="4" class="indent">
              <div class="readmo">
                <input type="checkbox" id="toggler" checked="false" /> 
                <label for="toggler"></label>
                <p>{{ implode('<br>', $ranges) }}</p>
              </div>
              
            </td>
        </tr>
        <tr>
            <td colspan="4" class="label">Shipping Locations</td>
        </tr>
        <tr>
            <td colspan="4"><img src="https://qeautoparts.com/ebay/map.gif"></td>
        </tr>
        <tr>
            <td colspan="4"><img src="https://qeautoparts.com/ebay/options.gif"></td>
        </tr>
        <tr>
            <td colspan="4"><img src="https://qeautoparts.com/ebay/shipping.gif"></td>
        </tr>
        <tr>
            <td colspan="4" class="label">Return Policy</td>
        </tr>
        <tr>
            <td colspan="4"><img src="https://qeautoparts.com/ebay/return.gif"></td>
        </tr>
        <tr>
            <td colspan="4" class="label">Warranty</td>
        </tr>
        <tr>
            <td colspan="4"><img src="https://qeautoparts.com/ebay/warranty.gif"></td>
        </tr>
        <tr>
            <td colspan="4" class="label">Vehicle Application</td>
        </tr>
        <tr>
            <td colspan="4"><img src="https://qeautoparts.com/ebay/application.gif"></td>
        </tr>
    </table>

    <table class="qe col-3 hide-xs" cellpadding="5px" cellspacing="8x" >
        <tr>
            <td colspan="4" class="label">Vehicle Compatibility</td>
        </tr>
        <tr>
            <td colspan="4" class="indent">
            {{ implode('<br>', $ranges) }}
            </td>
        </tr>
    </table>
    <div vocab="http://schema.org/" typeof="Product" style="display: none">
      <span property="description">
        <p>{{ implode('<br>', $desc) }}</p>
        <p>{{{ $condition }}} - {{{ $brand }}}</p>
        <p>{{{ implode(' / ', array_keys($mpns)) }}}</p>
        <p>{{ implode('<br>', $notes) }}</p>
      </span>
    </div>
</div>
</body>
</html>