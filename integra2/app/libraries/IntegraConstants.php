<?php

class OrderStatus
{
    const Unspecified = 0;
    const Scheduled = 1;
    const ItemOrdered = 2;
    const ForDispatch = 3;
    const Complete = 4;
    const Cancelled = 90;
    const PaymentPending = 91;
    const ReturnPending = 92;
    const ReturnComplete = 93;
    const Error = 99;
}
