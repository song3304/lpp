<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CatalogSeeds extends Migration
{
	/**
	 * Run the migrations.
	 *
	 * @return  void
	 */
	public function up()
	{
		\DB::transaction(function() {
			\Illuminate\Database\Eloquent\Model::unguard(true);
			\DB::statement('SET FOREIGN_KEY_CHECKS=0;');
			\DB::table('catalogs')->truncate();
			\DB::statement('SET FOREIGN_KEY_CHECKS=1;');
			\App\Catalog::forceCreate([
				'id' => 1,
				'name' => 'fields',
				'title' => '字段',
			])->forceCreate([
				'id' => 2,
				'name' => 'status',
				'title' => '状态',
			])->forceCreate([
				'id' => 3,
				'name' => 'news',
				'title' => '网站栏目',
			])->forceCreate([
				'id' => 4,
				'name' => '4',
				'title' => '',
			])->forceCreate([
				'id' => 5,
				'name' => '5',
				'title' => '',
			])->forceCreate([
				'id' => 6,
				'name' => '6',
				'title' => '',
			])->forceCreate([
				'id' => 7,
				'name' => '7',
				'title' => '',
			])->forceCreate([
				'id' => 8,
				'name' => '8',
				'title' => '',
			])->forceCreate([
				'id' => 9,
				'name' => '9',
				'title' => '',
			])->forceCreate([
				'id' => 10,
				'name' => '10',
				'title' => '',
			])->forceCreate([
				'id' => 0,
				'name' => '',
				'title' => '无'
			]);
			\DB::statement("ALTER TABLE `catalogs` AUTO_INCREMENT = 11;");
			\DB::statement("UPDATE `catalogs` SET `path` = '/0/', `id` = 0 WHERE `id` = 11;");

			$fields = [
				'gender|性别' => [
					'male|男' => [],
					'female|女' => [],
				],
			];
			$status = [
			];

			\Plugins\Catalog\App\Catalog::import($fields, \App\Catalog::findByName('fields'));
			\Plugins\Catalog\App\Catalog::import($status, \App\Catalog::findByName('status'));

			//添加权限
// 			\App\Permission::import([
// 				'catalog' => '系统分类',
// 			]);
			foreach([
			    'catalog' => '系统分类'
			] as $k => $v) {
			    foreach([
			        'view' => '查看',
			        'create' => '新建',
			        'edit' => '编辑',
			        'destroy' => '删除',
			        'export' => '导出'
			    ] as $k1 => $v1) {
			        \App\Permission::create([
			            'name' => $k.'.'.$k1,
			            'display_name' => '允许'.$v1.$v,
			        ]);
			    }
			}
			\App\Role::findByName('super')->perms()->sync(\App\Permission::all());

			\Illuminate\Database\Eloquent\Model::unguard(false);
		});
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return  void
	 */
	public function down()
	{

	}
}
