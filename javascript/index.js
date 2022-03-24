var items = [
	{
		'iscode':"01",
		'istyle':'A/S',
		'iscount':3,
	},
	{
		'iscode':"02",
		'istyle':'A/S',
		'iscount':3,
	},
	{
		'iscode':"02",
		'istyle':'M',
		'iscount':2,
	},
	{
		'iscode':"02",
		'istyle':'L',
		'iscount':2,
	},
	{
		'iscode':"02",
		'istyle':'XXL',
		'iscount':2,
	},
	{
		'iscode':"01",
		'istyle':'XXL',
		'iscount':2,
	},
	{
		'iscode':"03",
		'istyle':'XXL',
		'iscount':2,
	},
];

var displayItems = {};
var iscodes = items.map((v,i) => {
	return `${v.iscode}`;
});


iscodes = new Set(iscodes);
diffIscodes = [...iscodes];


diffIscodes.forEach((v,i) => {
	displayItems = {...displayItems,[v]:{ 'istyle':[],'iscount':[]} };
});

items.forEach((item,index) => {
	let iscode = item.iscode;
	let {[iscode]:old_iscode} = displayItems;
	let {istyle,iscount} = old_iscode;  
	displayItems = {
		...displayItems,  
		[item.iscode]:{ 
			'istyle':[...istyle,item.istyle],'iscount':[...iscount,item.iscount] 
		}
	}
});
console.log(displayItems);
