import { OrdersPage } from '@/components/admin/orders/orders-page';
import type { ComponentProps } from 'react';

type Props = Omit<ComponentProps<typeof OrdersPage>, 'segment'>;

export default function RegularOrders(props: Props) {
    return <OrdersPage segment="regular" {...props} />;
}
