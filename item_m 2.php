<?php
if (!defined('BASEPATH')) {
	exit('No direct script access allowed');
}

class item_m extends CI_Model {

	public $variable;

	public function __construct() {
		parent::__construct();
		$this->load->database();
	}

	public function addItemCategory($item_category) {
		$this->db->insert('item_category', $item_category);
		return $this->db->insert_id();
	}

	public function test() {
		$rs = array();
		$items = $this->db->get_where('item', array('org_id' => 1))->result_array();
		foreach ($items as $item) {
			if (is_null($item['factor'])) {
				$unit = $this->db->get_where('unit', array('id' => $item['major_unit']))->row();
				$major = intval($item['stock'] / $unit->factor);
				$minor = $item['stock'] - $major * $unit->factor;
				$item['major_amount'] = $major;
				$item['minor_amount'] = $minor;
				$item['major_symbol'] = $unit->symbol_major;
				$item['minor_symbol'] = $unit->symbol_minor;
			} else {
				$major_unit = $this->db->get_where('unit', array('id' => $item['major_unit']))->row();
				$minor_unit = $this->db->get_where('unit', array('id' => $item['minor_unit']))->row();
				$major = intval($item['stock'] / $item['factor']);
				$minor = $item['stock'] - $major * $item['factor'];
				$item['major_amount'] = $major;
				$item['minor_amount'] = $minor;
				$item['major_symbol'] = $major_unit->symbol_major;
				$item['minor_symbol'] = $minor_unit->symbol_minor;
			}
			$rs[] = $item;

		}
		return $rs;
	}

	public function getCat($id) {
		$result = $this->db->get_where('item_category', array('id' => $id))->row();
		return $result;
	}
	public function selectItem($id) {
		$items = $this->db->where('item.id', $id)->select('item.id,item.name,item.category_id,item.stock,item.cost,unit.symbol_minor as symbol_minor,unit.symbol_major, unit.range as unit_range')->from('item')->join('unit', 'unit.id=item.minor_unit')->get();
		return $items->row();
	}

	public function updateItemCategory($item_category) {
		$this->db->where('id', $item_category->id)->update('item_category', $item_category);
		return $this->db->affected_rows();
	}

	public function getItems($cat_id) {
		$all = array();
		$items = $this->db->where('item.category_id', $cat_id)
		              ->select('item.id,item.name,item.is_unique,item.norm_amount,
            item.image,item.usage,item.category_id,item.stock,unit.symbol_minor as symbol_minor,unit.symbol_major
            ,unit.factor as unit_factor')->order_by('item.name', 'ASC')->from('item')
		              ->join('unit', 'unit.id=item.minor_unit')->get()->result_array();
		foreach ($items as $item) {
			if ($item['is_unique'] == 0) {
				$sub_items = $this->db->where('item_to_item.item_id', $item['id'])
				                  ->select('item_to_item.sub_id as item_id,item_to_item.portion,item.name,item_category.name as cat_name, item_category.type as cat_type')
				                  ->from('item_to_item')
				                  ->join('item', 'item_to_item.sub_id=item.id')
				                  ->join('item_category', 'item.category_id = item_category.id')
				                  ->get()->result_array();
				$item['sub_items'] = $sub_items;
			}
			$all[] = $item;
		}
		// return $items->result_array();
		return $all;
	}

	public function new_getitems($cat_id) {
		$rs = array();
		$items = $this->db->get_where('item', array('category_id' => $cat_id, 'is_active' => 1))->result_array();
		foreach ($items as $item) {
			if ($item['major_unit'] == $item['minor_unit']) {
				$unit = $this->db->get_where('unit', array('id' => $item['major_unit']))->row();
				if (is_object($unit)) {
					$major = intval($item['stock'] / $unit->factor);
					$minor = $item['stock'] - $major * $unit->factor;
					$item['factor'] = $unit->factor;
					$item['major_amount'] = $major;
					$item['minor_amount'] = $minor;
					$item['major_symbol'] = $unit->symbol_major;
					$item['minor_symbol'] = $unit->symbol_minor;
				}
			} else {
				$major_unit = $this->db->get_where('unit', array('id' => $item['major_unit']))->row();
				$minor_unit = $this->db->get_where('unit', array('id' => $item['minor_unit']))->row();
				$major = intval($item['stock'] / $item['factor']);
				$minor = $item['stock'] - $major * $item['factor'];
				$item['major_amount'] = $major;
				$item['minor_amount'] = $minor;
				if (is_object($major_unit)) 
					$item['major_symbol'] = $major_unit->symbol_major;
				if (is_object($minor_unit)) 
					$item['minor_symbol'] = $minor_unit->symbol_minor;
			}

			if ($item['is_unique'] == 0) {
				$sub_items = $this->db->where('item_to_item.item_id', $item['id'])
				                  ->select('item_to_item.sub_id as item_id,item_to_item.portion,item.name,item_category.name as cat_name, item_category.type as cat_type')
				                  ->from('item_to_item')
				                  ->join('item', 'item_to_item.sub_id=item.id')
				                  ->join('item_category', 'item.category_id = item_category.id')
				                  ->get()->result_array();
				$item['sub_items'] = $sub_items;
			}
			$rs[] = $item;

		}
		return $rs;
	}

	public function getAllItems($org_id) {
		$all = array();
		$categories = $this->selectCategory($org_id);
		if ($categories == 0) {
			return $all;
		} else {
			foreach ($categories as $cat) {
				$items = $this->new_getitems($cat['id']);
				$cat['items'] = $items;
				$all[] = $cat;
			}
			return $all;
		}
	}

	public function selectCategory($org_id) {
		$categories = $this->db->get_where('item_category', array('org_id' => $org_id, 'is_active' => 1));
		if ($categories->num_rows() > 0) {
			return $categories->result_array();
		} else {
			return 0;
		}
	}

	public function addItem($item) {
		$this->db->insert('item', $item);
		return $this->db->insert_id();
	}

	public function itemExists($org_id, $name) {
		$item = $this->db->get_where('item', array('org_id' => $org_id, 'name' => $name));
		if ($item->num_rows() != 0) {
			return true;
		} else {
			return false;
		}
	}

	public function updateItem($item) {
		$this->db->where('id', $item->id)->update('item', $item);
		return $this->db->affected_rows();
	}

	public function deleteItem($id) {
		$this->db->where('id', $id)->update('item',array('is_active'=>0));
		return $this->db->affected_rows();
	}

	public function deleteCategory($id) {
		$this->db->where('id', $id)->update('item_category',array('is_active'=>0));
		return $this->db->affected_rows();
	}

	public function insertItem($item) {
		// $this->db->insert('order_to_item', $item);
		// $current_item = $this->get_item($item->item_id);
		// $current_stock = $current_item->stock;
		// $new_stock = $current_stock + $item->amount;
		// $new_data = new stdClass();
		// $new_data->id = $current_item->id;
		// $new_data->stock = $new_stock;
		// $rs = $this->updateItem($new_data);

		$factor = 0;
		$i = $this->db->get_where('item', array('id' => $item->item_id))->row();
		if (isset($item->factor)) {
			if ($item->factor == 2) {
				if ($i->major_unit == $i->minor_unit) {
					$factor = $this->db->select('factor')->get_where('unit', array('id' => $i->major_unit))->row()->factor;
				} else {
					$factor = $i->factor1;
				}
			} elseif ($item->factor == 3) {
				if ($i->major_unit == $i->minor_unit) {
					$factor = $this->db->select('factor')->get_where('unit', array('id' => $i->major_unit))->row()->factor;
				} else {
					$factor = $i->factor2;
				}
			} else {
				if ($i->major_unit == $i->minor_unit) {
					$factor = $this->db->select('factor')->get_where('unit', array('id' => $i->major_unit))->row()->factor;
				} else {
					$factor = $i->factor;
				}

			}
			unset($item->factor);
		} else {
			if ($i->major_unit == $i->minor_unit) {
				$factor = $this->db->select('factor')->get_where('unit', array('id' => $i->major_unit))->row()->factor;
			} else {
				$factor = $i->factor;
			}
		}

		$amount = $item->quantity * $factor;
		$item->amount = $amount;

		$this->db->insert('order_to_item', $item);
	}

	public function add_item_order($item_order) {

		$this->db->insert('item_order', $item_order);
		return $this->db->insert_id();
	}

	public function payment_info($data) {
		$this->db->insert('item_order_payment', $data);
		$id = $this->db->insert_id();
		$this->check_allpaid($data->order_id);
		return $id;
	}

	public function get_item($id) {
		$rs = $this->db->get_where('item', array('id' => $id));
		return $rs->row();
	}

	public function itemCount($cat_id) {
		$rs = $this->db->get_where('item', array('category_id' => $cat_id, 'is_active' => 1))->result_array();
		return count($rs);
	}

	public function order_history($start, $end, $org_id) {
		$start = $start.' '.'00:00:00';
		$end = $end.' '.'23:59:59';

		$all = new stdclass;
		$paid_array = array();
		$not_paid_array = array();

		$paid_orders = $this->db->where('item_order.order_date >=', $start)
		                    ->where('item_order.order_date <=', $end)
		                    ->where('item_order.org_id', $org_id)
		                    ->select('item_order.id,item_order.employee_name,DATE(item_order.order_date) as order_date,
		                    	supplier.name as supplier_name')
		                    ->from('item_order')
		                    ->join('supplier', 'item_order.supplier_id=supplier.id')->get()->result_array();

		foreach ($paid_orders as $order) {

			$payments = $this->db->select('cash,card,bank,created')
								->get_where('item_order_payment', array('order_id' => $order['id']))
								->result_array();

			$items = $this->db->where('item_order_id', $order['id'])
							->select('order_to_item.unit_price,order_to_item.quantity,item.name, 
				(order_to_item.quantity*order_to_item.unit_price) as total, order_to_item.amount, item.minor_unit')
			              ->from('order_to_item')->join('item', 'order_to_item.item_id=item.id')
			              ->get()->result_array();

			$all_items = array();

			foreach ($items as $item) {
				$symbol_minor = $this->db->select('symbol_minor')
										->get_where('unit', array('id' => $item['minor_unit']))
										->row()
										->symbol_minor;
										
				$item['symbol_minor'] = $symbol_minor;
				unset($item['minor_unit']);
				$all_items[] = $item;
			}

			$order['payment_info'] = $payments;
			$order['items'] = $all_items;
			$paid_array[] = $order;
		}
		return $paid_array;
	}

	public function check_allpaid($order_id) {
		$total = $this->order_total($order_id);
		$paid_amount = $this->order_paid($order_id);
		if ($paid_amount < $total) {

		} else {
			$this->db->where('id', $order_id)->update('item_order', array('is_paid' => 1));
		}
	}

	public function order_paid($order_id) {
		$query = "SELECT SUM(  `cash` ) AS cash,SUM(  `card` ) AS card,SUM(  `bank` ) AS bank FROM  `item_order_payment`  WHERE  `order_id` ={$order_id} GROUP BY  `order_id`";
		$result = $this->db->query($query)->row();
		$total = $result->cash + $result->card + $result->bank;
		return $total;
	}

	public function order_total($order_id) {
		$query = "SELECT SUM(  `unit_price`*`quantity` ) AS total FROM  `order_to_item`  WHERE  `item_order_id` ={$order_id} GROUP BY  `item_order_id`";
		$total = $this->db->query($query)->row()->total;
		return $total;
	}

	public function find() {
		$query = "SELECT item.id, item.name,item_category.name as cat_name
            FROM item
            INNER JOIN item_category ON item.category_id = item_category.id
            INNER JOIN (
            SELECT name
            FROM item
            GROUP BY name
            HAVING count(name) > 1
            ) dup ON item.name = dup.name
            ORDER BY item.name";
		$rs = $this->db->query($query)->result_array();
		return $rs;
	}

	public function addSubItems($item) {
		$this->db->insert('item_to_item', $item);
	}

	public function removeSubItems($item_id) {
		$this->db->where('item_id', $item_id)->delete('item_to_item');
	}

	public function get_current_stock($id) {
		$stock = $this->db->get_where('item', array('id' => $id))->row()->stock;
		return $stock;
	}

	public function calculate_sub_items($item_id, $amount) {
		$sub_items = $this->db->where('item_id', $item_id)->select('item.id,(item_to_item.portion*{$amount}) as portion,item.item.stock')->from('item_to_item')
		                  ->join('item', 'item_to_item.sub_id=item.id')->get()->result_array();
		foreach ($sub_items as $item) {
			$updated_stock = $item['stock'] - $item['portion'];
			$this->db->where('id', $item['id'])->update('item', array('stock' => $updated_stock));
		}
	}

	public function item_to_sector($data) {
		$this->db->insert('item_to_sector', $data);
	}

	public function items_to_sector($items_to_sector) {

		$item_count = count($items_to_sector->items);
		$check_count = 0;

		foreach ($items_to_sector->items as $item_to_sector) {

			$available_stock = $this->item_m->get_available_stock($item_to_sector->item_id);

			if ($available_stock > 0) {
				$data = new stdclass();
				$data = $item_to_sector;
				$data->org_id = $items_to_sector->org_id;
				$data->sector_id = $items_to_sector->sector_id;
				$data->user_id_out = $items_to_sector->user_id_out;
				$data->user_id_in = $items_to_sector->user_id_in;
				$result = $this->db->insert('item_to_sector', $data);
				if($result)
					$check_count++;
			}else{
				continue;
			}
		}
		if ($check_count > 0)
			return $check_count;
		else 
			return 0;
	}

	public function sectorItems($sector_id) {
		$rs = array();
		$items = $this->db->where('sector_id', $sector_id)
						->where('item.is_active',1)
						->select('item_to_sector.item_id,item.factor,item.major_unit,item.minor_unit,
			item.name,item.image,SUM(item_to_sector.amount) as stock')
		              ->from('item_to_sector')->join('item', 'item_to_sector.item_id=item.id')->group_by('item_to_sector.item_id')->get()->result_array();
		              
		$date = date("Y-m-d");
		$date1 = $date.' '.'23:59:59';
		foreach ($items as $item) {
			if ($item['major_unit'] == $item['minor_unit']) {
				$unit = $this->db->get_where('unit', array('id' => $item['major_unit']))->row();
				$major = intval($item['stock'] / $unit->factor);
				$minor = $item['stock'] - $major * $unit->factor;
				$item['factor'] = $unit->factor;
				$item['major_amount'] = $major;
				$item['minor_amount'] = $minor;
				$item['major_symbol'] = $unit->symbol_major;
				$item['minor_symbol'] = $unit->symbol_minor;
			} else {
				$major_unit = $this->db->get_where('unit', array('id' => $item['major_unit']))->row();
				$minor_unit = $this->db->get_where('unit', array('id' => $item['minor_unit']))->row();
				$major = intval($item['stock'] / $item['factor']);
				$minor = $item['stock'] - $major * $item['factor'];
				$item['major_amount'] = $major;
				$item['minor_amount'] = $minor;
				$item['major_symbol'] = $major_unit->symbol_major;
				$item['minor_symbol'] = $minor_unit->symbol_minor;
			}
			$out = $this->item_out($item['item_id'], $date, $sector_id);
			$in = $this->item_in($item['item_id'], $date, $sector_id);
			$out_today = $this->item_out_today($item['item_id'], $date, $sector_id);
			$in_today = $this->item_in_today($item['item_id'], $date, $sector_id);

			$last_stock_balancing = $this->getLastStockBalancing($date1,$sector_id,$item['item_id']);
			$expense = $this->getDisburseFinalStock($date1, $sector_id, $item['item_id']);

			$item['last_stock'] = $in - $out + $in_today - $out_today + $last_stock_balancing - $expense;

			$rs[] = $item;
		}
		return $rs;
	}

	public function stockReport($org_id, $date, $user_id, $access_token) {
		$emp = $this->db->get_where('employee', array('id'=>$user_id))->row();
		$date = $date.' '.'23:59:59';
		if (empty($emp)) {
			$all = array();
			$sectors = $this->db->select('id,name,is_deleted')->get_where('sector', array('org_id' => $org_id))->result_array();

			foreach ($sectors as $sector) {

				if ($sector['is_deleted'] == 1) {
					continue;
				}

				$data = $this->firstStock($sector['id'], $date);
				$sector['items'] = $data;
				$all[] = $sector;
			}
			return $all;

		} else {
			if ($emp->group_id == 4 || $emp->group_id == 7) {
				$ses = $this->db->get_where('user_sessions', array('user_id'=>$user_id, 'access_token'=>$access_token))->row();
				
				if (empty($ses)) {
					$all = array();
					$sectors = $this->db->select('id,name,is_deleted')->get_where('sector', array('org_id' => $org_id))->result_array();

					foreach ($sectors as $sector) {

						if ($sector['is_deleted'] == 1) {
							continue;
						}

						$data = $this->firstStock($sector['id'], $date);
						$sector['items'] = $data;
						$all[] = $sector;
					}
					return $all;

				} else {
					$all = array();
					$sectors = $this->db->select('id,name,is_deleted')->get_where('sector', array('id' => $ses->sector_id, 'org_id' => $org_id))->result_array();

					foreach ($sectors as $sector) {

						if ($sector['is_deleted'] == 1) {
							continue;
						}

						$data = $this->firstStock($sector['id'], $date);
						$sector['items'] = $data;
						$all[] = $sector;
					}
					return $all;
				}

			} else {
				$all = array();
				$sectors = $this->db->select('id,name,is_deleted')->get_where('sector', array('org_id' => $org_id))->result_array();

				foreach ($sectors as $sector) {

					if ($sector['is_deleted'] == 1) {
						continue;
					}

					$data = $this->firstStock($sector['id'], $date);
					$sector['items'] = $data;
					$all[] = $sector;
				}
				return $all;
			}
		}
	}

	public function new_stockReport($org_id, $start_date, $end_date, $user_id, $access_token){

		$emp = $this->db->get_where('employee', array('id'=>$user_id))->row();

		$start_date = $start_date.' '.'00:00:00';
		$end_date = $end_date.' 23:59:59';
		if (empty($emp)) {
			$all = array();
			$sectors = $this->db->select('id,name,is_deleted')->get_where('sector', array('org_id' => $org_id))->result_array();

			foreach ($sectors as $sector) {

				if ($sector['is_deleted'] == 1) {
					continue;
				}

				$data = $this->new_firstStock($sector['id'], $start_date, $end_date);
				$sector['items'] = $data;
				$all[] = $sector;
			}
			return $all;

		} else {
			if ($emp->group_id == 4 || $emp->group_id == 7) {
				$ses = $this->db->get_where('user_sessions', array('user_id'=>$user_id, 'access_token'=>$access_token))->row();
				
				if (empty($ses)) {
					$all = array();
					$sectors = $this->db->select('id,name,is_deleted')->get_where('sector', array('org_id' => $org_id))->result_array();

					foreach ($sectors as $sector) {

						if ($sector['is_deleted'] == 1) {
							continue;
						}

						$data = $this->new_firstStock($sector['id'], $start_date, $end_date);
						$sector['items'] = $data;
						$all[] = $sector;
					}
					return $all;

				} else {
					$all = array();
					$sectors = $this->db->select('id,name,is_deleted')->get_where('sector', array('id' => $ses->sector_id, 'org_id' => $org_id))->result_array();

					foreach ($sectors as $sector) {

						if ($sector['is_deleted'] == 1) {
							continue;
						}

						$data = $this->new_firstStock($sector['id'], $start_date, $end_date);
						$sector['items'] = $data;
						$all[] = $sector;
					}
					return $all;
				}

			} else {
				$all = array();
				$sectors = $this->db->select('id,name,is_deleted')->get_where('sector', array('org_id' => $org_id))->result_array();

				foreach ($sectors as $sector) {

					if ($sector['is_deleted'] == 1) {
						continue;
					}

					$data = $this->new_firstStock($sector['id'], $start_date, $end_date);
					$sector['items'] = $data;
					$all[] = $sector;
				}
				return $all;
			}
		}
	}

	public function firstStock($sector_id, $begin_date) {

		$items = $this->db->where('item_to_sector.created <=', $date)
		              ->where('item_to_sector.sector_id', $sector_id)
		              ->where('item.is_active',1)
		              ->select('item.id, item.factor,item.major_unit,item.minor_unit,item.name')
		              ->from('item_to_sector')
		              ->join('item', 'item_to_sector.item_id=item.id')
		              ->group_by('item_to_sector.item_id')
		              ->get()->result_array();

		$all = array();
		foreach ($items as $item) {

			$out = $this->item_out($item['id'], $date, $sector_id);
			$in = $this->item_in($item['id'], $date, $sector_id);
			$out_today = $this->item_out_today($item['id'], $date, $sector_id);
			$in_today = $this->item_in_today($item['id'], $date, $sector_id);

			$all_past_last_stock_balancing = $this->getAllPastBalancing($date,$sector_id,$item['id']);
			$item['dif_first_stock'] = $all_past_last_stock_balancing;

			$item['first_stock'] = $in - $out + $all_past_last_stock_balancing;
			$item['item_in'] = $in_today;
			$item['item_out'] = $out_today;

			$last_stock = $in - $out + $in_today - $out_today;

			$item['last_stock'] = $last_stock;

			$last_stock_balancing = $this->getLastStockBalancing($date,$sector_id,$item['id']);

			$edited_last_stock = $last_stock + $last_stock_balancing;
			
			$item['dif_last_stock'] = $last_stock_balancing;
			//тайлбар
			$notes = '';
			$first_stock_notes = $this->getFirstStockNotes($date,$sector_id,$item['id']);
			if ($first_stock_notes) {
				foreach ($first_stock_notes as $note) {
					$notes = $notes.'  '.$note['note'];
				}
			}

			if ($last_stock_balancing) {
				$last_stock_notes = $this->getLastStockNotes($date,$sector_id,$item['id']);
				if ($last_stock_notes) {
					foreach ($last_stock_notes as $note) {
						$notes = $notes.'  '.$note['note'];
					}
				}
			}

			$expense = $this->getDisburseFinalStock($date, $sector_id, $item['id']);
			$item['dif_expense'] = $expense;
			if ($expense) {
				$expense_notes = $this->getExpenseNotes($date, $sector_id,$item['id']);
				if ($expense_notes) {
					foreach ($expense_notes as $note) {
						$notes = $notes.'  '.$note['note'];
					}
				}
			}
			
			

			$item['note'] = $notes;
			$item['edited_last_stock'] = $edited_last_stock - $expense;
			$result = $this->get_unit_price_of_last_ordered_item($item['id']);
				if ($result)
					$item['by_amount_of_money'] = $item['edited_last_stock']/$result->measured_amount * $result->unit_price; 
				else
					$item['by_amount_of_money'] = 0;

			if ($item['major_unit'] == $item['minor_unit']) {
				$unit = $this->db->get_where('unit', array('id' => $item['major_unit']))->row();
				$major = intval($item['first_stock'] / $unit->factor);
				$major_last = intval($item['last_stock'] / $unit->factor);
				$minor = $item['first_stock'] - $major * $unit->factor;
				$minor_last = $item['last_stock'] - $major * $unit->factor;
				$item['factor'] = $unit->factor;
				$item['major_first'] = $major;
				$item['minor_first'] = $minor;
				$item['major_last'] = $major;
				$item['minor_last'] = $minor;
				$item['major_symbol'] = $unit->symbol_major;
				$item['minor_symbol'] = $unit->symbol_minor;
			} else {
				$major_unit = $this->db->get_where('unit', array('id' => $item['major_unit']))->row();
				$minor_unit = $this->db->get_where('unit', array('id' => $item['minor_unit']))->row();
				$major = intval($item['first_stock'] / $item['factor']);
				$major_last = intval($item['last_stock'] / $item['factor']);
				$minor = $item['first_stock'] - $major * $item['factor'];
				$minor_last = $item['last_stock'] - $major * $item['factor'];
				$item['factor'] = $item['factor'];
				$item['major_first'] = $major;
				$item['minor_first'] = $minor;
				$item['major_last'] = $major;
				$item['minor_last'] = $minor;
				$item['major_symbol'] = $major_unit->symbol_major;
				$item['minor_symbol'] = $minor_unit->symbol_minor;
			}

			$all[] = $item;
		}
		return $all;
	}

	public function new_firstStock($sector_id, $begin_date, $end_date) {

		$items = $this->db->where('item_to_sector.created >=', $begin_date)
						->where('item_to_sector.created <=', $end_date)
		              ->where('item_to_sector.sector_id', $sector_id)
		              ->where('item.is_active',1)
		              ->select('item.id, item.factor,item.major_unit,item.minor_unit,item.name')
		              ->from('item_to_sector')
		              ->join('item', 'item_to_sector.item_id=item.id')
		              ->group_by('item_to_sector.item_id')
		              ->get()->result_array();

		$all = array();
		foreach ($items as $item) {

			$out = $this->item_out($item['id'], $end_date, $sector_id);
			$in = $this->item_in($item['id'], $end_date, $sector_id);
			$out_between_dates = $this->item_out_between_dates($item['id'], $begin_date, $end_date, $sector_id);
			$in_between_dates = $this->item_in_between_dates($item['id'], $begin_date, $end_date, $sector_id);

			$all_past_last_stock_balancing = $this->getAllPastBalancing($begin_date, $sector_id, $item['id']);
			$item['dif_first_stock'] = $all_past_last_stock_balancing;

			$item['first_stock'] = $in - $out + $all_past_last_stock_balancing;
			$item['item_in'] = $in_today;
			$item['item_out'] = $out_today;

			$last_stock = $in - $out + $in_today - $out_today;

			$item['last_stock'] = $last_stock;

			$last_stock_balancing = $this->getLastStockBalancing($begin_date, $sector_id, $item['id']);

			$edited_last_stock = $last_stock + $last_stock_balancing;
			
			$item['dif_last_stock'] = $last_stock_balancing;
			//тайлбар
			$notes = '';
			$first_stock_notes = $this->getFirstStockNotes($begin_date, $sector_id, $item['id']);
			if ($first_stock_notes) {
				foreach ($first_stock_notes as $note) {
					$notes = $notes.'  '.$note['note'];
				}
			}

			if ($last_stock_balancing) {
				$last_stock_notes = $this->getLastStockNotes($date,$sector_id,$item['id']);
				if ($last_stock_notes) {
					foreach ($last_stock_notes as $note) {
						$notes = $notes.'  '.$note['note'];
					}
				}
			}

			$expense = $this->getDisburseFinalStock($date, $sector_id, $item['id']);
			$item['dif_expense'] = $expense;
			if ($expense) {
				$expense_notes = $this->getExpenseNotes($date, $sector_id,$item['id']);
				if ($expense_notes) {
					foreach ($expense_notes as $note) {
						$notes = $notes.'  '.$note['note'];
					}
				}
			}
			
			

			$item['note'] = $notes;
			$item['edited_last_stock'] = $edited_last_stock - $expense;
			$result = $this->get_unit_price_of_last_ordered_item($item['id']);
				if ($result)
					$item['by_amount_of_money'] = $item['edited_last_stock']/$result->measured_amount * $result->unit_price; 
				else
					$item['by_amount_of_money'] = 0;

			if ($item['major_unit'] == $item['minor_unit']) {
				$unit = $this->db->get_where('unit', array('id' => $item['major_unit']))->row();
				$major = intval($item['first_stock'] / $unit->factor);
				$major_last = intval($item['last_stock'] / $unit->factor);
				$minor = $item['first_stock'] - $major * $unit->factor;
				$minor_last = $item['last_stock'] - $major * $unit->factor;
				$item['factor'] = $unit->factor;
				$item['major_first'] = $major;
				$item['minor_first'] = $minor;
				$item['major_last'] = $major;
				$item['minor_last'] = $minor;
				$item['major_symbol'] = $unit->symbol_major;
				$item['minor_symbol'] = $unit->symbol_minor;
			} else {
				$major_unit = $this->db->get_where('unit', array('id' => $item['major_unit']))->row();
				$minor_unit = $this->db->get_where('unit', array('id' => $item['minor_unit']))->row();
				$major = intval($item['first_stock'] / $item['factor']);
				$major_last = intval($item['last_stock'] / $item['factor']);
				$minor = $item['first_stock'] - $major * $item['factor'];
				$minor_last = $item['last_stock'] - $major * $item['factor'];
				$item['factor'] = $item['factor'];
				$item['major_first'] = $major;
				$item['minor_first'] = $minor;
				$item['major_last'] = $major;
				$item['minor_last'] = $minor;
				$item['major_symbol'] = $major_unit->symbol_major;
				$item['minor_symbol'] = $minor_unit->symbol_minor;
			}

			$all[] = $item;
		}
		return $all;
	}

	public function item_out_today($item_id, $date, $sector_id) {

		$date = date("Y-m-d",strtotime($date));

		$items = $this->db->get_where('product_to_item_inventory',
			array('item_id' => $item_id,
				'sector_id' => $sector_id,
				'date' => $date)
		)->result_array();

		$a = 0;

		foreach ($items as $item) {
			$a += $item['quantity'] * $item['abrasion'];
		}
		return $a;

		// $items = $this->db->where('order_to_product.inserted >=', $beginning)
		//               ->where('order_to_product.inserted <=', $ending)
		//               ->where('product_to_item.item_id', $item_id)
		//               ->where('sector.id', $sector_id)
		//               ->select('SUM(order_to_product.quantity) as quantity,product_to_item.abrasion')
		//               ->from('product_to_item')
		//               ->join('order_to_product', 'product_to_item.product_id=order_to_product.product_id')
		//               ->join('product', 'order_to_product.product_id=product.id')
		//               ->join('category', 'product.cat_id=category.id')
		//               ->join('sector', 'category.sector_id=sector.id')
		//               ->group_by('order_to_product.product_id')->get()->result_array();
		// $a = 0;

		// foreach ($items as $item) {
		// 	$a += $item['quantity'] * $item['abrasion'];
		// }
		// return $a;

	}

	public function item_out_between_dates($item_id, $begin_date, $end_date, $sector_id) {

		$beginning = date("Y-m-d",strtotime($begin_date));
		$ending = date("Y-m-d",strtotime($end_date));

		$items = $this->db->get_where('product_to_item_inventory',
			array('item_id' => $item_id,
				'sector_id' => $sector_id,
				'date' => $beginning
				'date' <= $ending)
		)->result_array();

		$a = 0;

		foreach ($items as $item) {
			$a += $item['quantity'] * $item['abrasion'];
		}
		return $a;
	}

	public function item_out($item_id, $date, $sector_id) {
		// $beginning = $date;
		$beginning = date('Y-m-d',strtotime($date));

		$items = $this->db->get_where('product_to_item_inventory',
			array('item_id' => $item_id,
				'date <' => $beginning,
				'sector_id' => $sector_id)
		)->result_array();

		$a = 0;

		foreach ($items as $item) {
			$a += $item['quantity'] * $item['abrasion'];
		}
		return $a;

		// $items = $this->db->where('order_to_product.inserted <', $beginning)
		//               ->where('product_to_item.item_id', $item_id)
		//               ->where('sector.id', $sector_id)
		//               ->select('SUM(order_to_product.quantity) as quantity,product_to_item.abrasion')
		//               ->from('product_to_item')
		//               ->join('order_to_product', 'product_to_item.product_id=order_to_product.product_id')
		//               ->join('product', 'order_to_product.product_id=product.id')
		//               ->join('category', 'product.cat_id=category.id')
		//               ->join('sector', 'category.sector_id=sector.id')
		//               ->group_by('order_to_product.product_id')->get()->result_array();
		// $a = 0;

		// foreach ($items as $item) {
		// 	$a += $item['quantity'] * $item['abrasion'];
		// }
		// return $a;

	}

	public function item_in($item_id, $date, $sector_id) {

		$date = date('Y-m-d',strtotime($date));

		$a = $this->db->where('created <', $date)
		          ->where('item_id', $item_id)
		          ->where('sector_id', $sector_id)->select('SUM(amount) as amount')
		          ->from('item_to_sector')->group_by('item_id')->get()->row();
		if ($a) {
			return $a->amount;
		}

		return 0;
	}

	public function item_in_today($item_id, $date, $sector_id) {

		$date = date("Y-m-d",strtotime($date));
		$beginning_of_day = $date.' '.'00:00:00';
		$ending_of_day = $date.' '.'23:59:59';

		$a = $this->db->where('created >=', $beginning_of_day)
					->where('created <=',$ending_of_day)
		          ->where('item_id', $item_id)
		          ->where('sector_id', $sector_id)->select('SUM(amount) as amount')
		          ->from('item_to_sector')->group_by('item_id')->get();
		if ($a->num_rows() != 0) {
			return $a->row()->amount;
		}
		return 0;
	}

	public function item_in_between_dates($item_id, $begin_date, $end_date, $sector_id) {

		$a = $this->db->where('created >=', $begin_date)
					->where('created <=',$end_date)
		          ->where('item_id', $item_id)
		          ->where('sector_id', $sector_id)->select('SUM(amount) as amount')
		          ->from('item_to_sector')->group_by('item_id')->get();
		if ($a->num_rows() != 0) {
			return $a->row()->amount;
		}
		return 0;
	}

	public function get_available_stock($item_id) {
		$totalAmount = $this->db->where('item_id', $item_id)
		                    ->select('sum(amount) as amount')
		                    ->from('order_to_item')
		                    ->get()->row()->amount;

		$sectorsAmount = $this->db->where('item_id', $item_id)
		                      ->select('sum(amount) as amount')
		                      ->from('item_to_sector')
		                      ->get()->row()->amount;

		$totalAmount = $totalAmount - $sectorsAmount;

		if ($totalAmount >= 0) {
			return $totalAmount;
		} else {
			return 0;
		}
	}
//SQUIRE
	public function get_available_stock_with_end_date($item_id, $end_date) {

		$totalAmount = $this->db->where('order_date <',$end_date)
								->where('item_id',$item_id)
								->select('sum(order_to_item.amount) as amount')
								->from('item_order')
								->join('order_to_item','item_order.id = order_to_item.item_order_id')
								->get()->row()->amount;

		$sectorsAmount = $this->db->where('item_id', $item_id)
								->where('created <', $end_date)
		                      ->select('sum(amount) as amount')
		                      ->from('item_to_sector')
		                      ->get()->row()->amount;

		$totalAmount = $totalAmount - $sectorsAmount;

		if ($totalAmount >= 0) {
			return $totalAmount;
		} else {
			return 0;
		}
	}

	public function get_warehouse_balancing($item_id, $end_date) {

		$total_warehouse_balancing = $this->db->where('item_id',$item_id)
									->where('created <',$end_date)
									->select('sum(stock_balancing) as total_warehouse_balancing')
									->from('warehouse_stock_balancing')
									->get()->row()->total_warehouse_balancing;

		$beginning_of_day = date('Y-m-d 00:00:00',strtotime($end_date));

		$info_of_the_day = new stdclass();
		$info_of_the_day = $this->db->where('item_id',$item_id)
									->where('created >=',$beginning_of_day)
									->where('created <=',$end_date)
									->select('GROUP_CONCAT(DISTINCT note ORDER BY created ASC SEPARATOR ",") as note,sum(stock_balancing) as stock_balancing',FALSE)
									->from('warehouse_stock_balancing')
									->get()->row();
		if (!$info_of_the_day->note) {
			$info_of_the_day->note = '';
		}

		if (!$info_of_the_day->stock_balancing) {
			$info_of_the_day->stock_balancing = 0;
		}

		if ($total_warehouse_balancing) {
			$info_of_the_day->total_warehouse_balancing = $total_warehouse_balancing;
		} else{
			$info_of_the_day->total_warehouse_balancing = 0;
		}					
		
		return $info_of_the_day;
	}
//
	public function getItemsForProduct($product_id) {
		$itemArray = $this->db->get_where('item', array('product' => $order_id))->result_array();
	}

	public function getAllPastBalancing($date,$sector_id,$item_id){

		$date = date('Y-m-d 00:00:00',strtotime($date));

		$result = $this->db->where('item_id',$item_id)
							->where('sector_id',$sector_id)
							->where('created <',$date)
							->select('SUM(last_stock_balancing) as last_stock_balancing')
							->from('section_item_stock_balancing')
							->group_by('item_id')->get()->row();
		if ($result) {
			return $result->last_stock_balancing;
		}
		return 0;
	}

	public function getLastStockBalancing($date,$sector_id,$item_id){
		$result = $this->db->where('item_id',$item_id)
							->where('sector_id',$sector_id)
							->where('created <=',$date)
							->select('SUM(last_stock_balancing) as last_stock_balancing')
							->from('section_item_stock_balancing')
							->group_by('item_id')->get()->row();
		if ($result) {
			return $result->last_stock_balancing;
		}
		return 0;
	}

	public function getDisburseFinalStock($date,$sector_id,$item_id){
		$result = $this->db->where('item_id',$item_id)
							->where('sector_id',$sector_id)
							->where('created <',$date)
							->select('SUM(expense) as expense')
							->from('section_item_expense')
							->group_by('item_id')->get()->row();
		if ($result) {
			return $result->expense;
		}
		return 0;
	}

	public function getLastStockNotes($date,$sector_id,$item_id){
		$beginning = date('Y-m-d 00:00:00',strtotime($date));

		$notes = $this->db->where('item_id',$item_id)
							->where('sector_id',$sector_id)
							->where('created >=',$beginning)
							->where('created <=',$date)
							->select('note')
							->from('section_item_stock_balancing')
							->get()->result_array();
		if ($notes) {
			return $notes;
		}
		return 0;
	}

	public function getFirstStockNotes($date,$sector_id,$item_id){
		$time = strtotime($date);
		$yesterday = date('Y-m-d H:m:s',$time - 60*60*24);
		$date = date('Y-m-d 00:00:00',strtotime($yesterday));
		$notes = $this->db->where('item_id',$item_id)
							->where('sector_id',$sector_id)
							->where('created <=',$yesterday)
							->where('created >=',$date)
							->where('is_first_stock_balancing',1)
							->select('note')
							->from('section_item_stock_balancing')
							->get()->result_array();
		if ($notes) {
			return $notes;
		}
		return 0;
	}
	
	public function getExpenseNotes($date,$sector_id,$item_id){
		$beginning = date('Y-m-d 00:00:00',strtotime($date));
		$notes = $this->db->where('item_id',$item_id)
							->where('sector_id',$sector_id)
							->where('created >=',$beginning)
							->where('created <=',$date)
							->select('note')
							->from('section_item_expense')
							->get()->result_array();
		if ($notes) {
			return $notes;
		}
		return 0;
	}

	public function get_unit_price_of_last_ordered_item($item_id){

		$query ="select unit_price,has_tax,quantity,amount from order_to_item where item_id =".$item_id." order by id DESC limit 1";
		$result = new stdclass();
	    $result = $this->db->query($query)->row();
	    if (empty($result)) {
	    	return 0;
	    }
	    $result->measured_amount = $result->amount / $result->quantity;
	    
	    if ($result->has_tax) {
	    	$result->unit_price = $result->unit_price/1.1;
	    }
	    
	    unset($result->amount);
	    unset($result->quantity);
	    unset($result->has_tax);
        return $result;
	}


	public function updateLastStockBalancing($data){
			$this->db->insert('section_item_stock_balancing',$data);
			return $this->db->insert_id();
	}

	public function updateWarehouseStockBalancing($data){
			$this->db->insert('warehouse_stock_balancing',$data);
			return $this->db->insert_id();
	}

	public function disburseFinalStock($data){
			$this->db->insert('section_item_expense',$data);
			return $this->db->insert_id();
	}
}

/* End of file  */

/* Location: ./application/models/ */