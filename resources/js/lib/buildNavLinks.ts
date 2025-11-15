import route from 'ziggy-js'


export type NavItem = {
    label: string
    href?: string
    route?: string
    children?: NavItem[]
}


export type NavContext = {
    channelId?: number | string
    productId?: number | string
    variantId?: number | string
    params?: Record<string, any>
}


function resolveRoute(name: string, ctx: NavContext): string {
    switch (name) {
        case 'channels.policy.edit':
            if (ctx.channelId == null) throw new Error('Missing channelId for channels.policy.edit')
            return route(name, ctx.channelId)
        case 'variants.inventory':
            if (ctx.productId == null || ctx.variantId == null) throw new Error('Missing productId or variantId for variants.inventory')
            return route(name, [ctx.productId, ctx.variantId])
        default:
            return route(name, ctx.params || {})
    }
}


export function buildNavLinks(items: NavItem[], ctx: NavContext = {}): NavItem[] {
    return items.map((i) => {
        const out: NavItem = { label: i.label }
        if (i.href) out.href = i.href
        else if (i.route) out.href = resolveRoute(i.route, ctx)
        if (i.children) out.children = buildNavLinks(i.children, ctx)
        return out
    })
}


export default buildNavLinks
