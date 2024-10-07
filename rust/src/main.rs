mod fq_api;
mod fq_struct;
use crate::fq_api::batch_full;
use crate::fq_struct::FqVariable;

#[tokio::main]
async fn main() {
	//println!("Hello, world!");

	//let item_ids = "7392244682832495129,7392447334413517337,7392543933567336985,7392610419279397401,7392843331383869977,7392909011617579545,7392990583624581657,7393206582584017433,7393285342490542617,7393351593497723417,7393539266896200217,7393649318462243353,7393732188027486745,7393938592134857241,7394064148893532697,7394117263747449369,7394321009760797209,7394427975812268569,7394444633637388825";
	let item_ids = "7392447334413517337";
	let Ok(_client) = reqwest::Client::builder().build()
	else { panic!("build client failed") };

	let ref var = FqVariable {
	    install_id: "4427064614339001".to_string(),
	    server_device_id: "4427064614334905".to_string(),
	    aid: "1967".to_string(),
	    update_version_code: "62532".to_string(),
	};
	//println!("{:#?}", var);
	let Ok(batch_full) = batch_full(&_client, var, item_ids, false).await
	else { panic!("batch_full failed") };

	let Ok(res) = batch_full.get_decrypt_contents(&_client, var).await
        else { panic!("get_decrypt_contents failed") };

	for line in res {
		println!("\n编号:\t{}\n标题:\t{}\n内容:\t{}", line.0, line.1, line.2);
	}

	/*let line1 = res.get(0).unwrap();
	println!("main > itemid:{}", line1.0.clone());
	println!("main > title:{}", line1.1.clone());
	println!("main > content:{}", line1.2.clone());*/

	//let payloads = FqRegisterKeyPayload::new(var) else { return };
	//println!("{:?}", &payloads);
	//println!("{:#?}", &payloads);
}