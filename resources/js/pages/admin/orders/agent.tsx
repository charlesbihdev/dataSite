import { OrdersPage } from '@/components/admin/orders/orders-page';
import type { ComponentProps } from 'react';

type Props = Omit<ComponentProps<typeof OrdersPage>, 'segment'>;

export default function AgentOrders(props: Props) {
    return <OrdersPage segment="agent" {...props} />;
}
