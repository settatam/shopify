export type NavItem = { label: string; destination: string };
export type PrimaryAction = { label: string; url?: string };
export type SecondaryAction = { label: string; url?: string };


export function useAppBridgeNav(){
    const app = (window as any).shopifyApp;
    if (!app) return { setTitle: (_:string)=>{}, setBreadcrumbs: (_:any)=>{}, setPrimary: (_:any)=>{}, setSecondary: (_:any)=>{}, setNav: (_:any)=>{} };
    const { TitleBar, NavigationMenu } = (window as any)['app-bridge'];


    let titleBar: any = null;
    let navMenu: any = null;


    function setTitle(title: string){
        if (titleBar) titleBar.unsubscribe();
        titleBar = TitleBar.create(app, { title });
    }

    function setBreadcrumbs(bcs: {content: string; url?: string}[]){
        if (!titleBar) titleBar = TitleBar.create(app, { title: document.title || 'App' });
        TitleBar.update(titleBar, { breadcrumbs: bcs.map(b => ({ content: b.content, url: b.url })) });
    }
    function setPrimary(a?: PrimaryAction){
        if (!titleBar) titleBar = TitleBar.create(app, { title: document.title || 'App' });
        TitleBar.update(titleBar, { primaryAction: a ? { content: a.label, url: a.url } : undefined });
    }
    function setSecondary(a?: SecondaryAction){
        if (!titleBar) titleBar = TitleBar.create(app, { title: document.title || 'App' });
        TitleBar.update(titleBar, { secondaryActions: a ? [{ content: a.label, url: a.url }] : [] });
    }
    function setNav(items: NavItem[]){
        if (navMenu) navMenu.unsubscribe();
        navMenu = NavigationMenu.create(app, { items: items.map(i => ({ label: i.label, destination: i.destination })) });
    }
    return { setTitle, setBreadcrumbs, setPrimary, setSecondary, setNav };
}
